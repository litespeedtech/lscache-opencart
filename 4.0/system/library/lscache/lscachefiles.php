<?php

/*
 *  LiteSpeed Cache - physical cache-file housekeeping.
 *
 *  The LiteSpeed purge API only marks entries stale; it never removes the cache
 *  files from disk. This helper locates the LiteSpeed cache store, validates it by
 *  the on-disk file signature, and deletes files (all, or older than N days) with
 *  strict path containment so it can never touch anything outside the cache root.
 *
 *  @since      2.5.1
 *  @license    https://opensource.org/licenses/GPL-3.0
 */

class LiteSpeedCacheFiles {

    // LiteSpeed cache files begin with this magic ("LSCHM-...").
    const MAGIC = 'LSCH';

    /*
     * Resolve the cache root. An explicit configured path wins; otherwise try the
     * standard LiteSpeed layouts and an .htaccess CacheRoot directive. Returns a
     * validated realpath (a directory that actually holds LiteSpeed cache files)
     * or '' when nothing valid is found.
     */
    public static function resolve($configured, $documentRoot) {
        $configured = trim((string)$configured);
        $documentRoot = rtrim((string)$documentRoot, '/\\');

        $candidates = array();
        if ($configured !== '') {
            $candidates[] = $configured;
        } else if ($documentRoot !== '') {
            $candidates[] = dirname($documentRoot) . '/cache'; // $VH_ROOT/cache (LSE default)
            $candidates[] = $documentRoot . '/cache';
            $ht = self::htaccessCacheRoot($documentRoot);
            if ($ht !== '') {
                $candidates[] = $ht;
            }
        }

        foreach ($candidates as $candidate) {
            $real = self::validate($candidate, $documentRoot);
            if ($real !== '') {
                return $real;
            }
        }
        return '';
    }

    /*
     * Validate that $dir is a LiteSpeed cache store and is safe to operate on.
     * Returns the realpath on success, '' otherwise.
     */
    public static function validate($dir, $documentRoot) {
        $dir = trim((string)$dir);
        if ($dir === '' || !is_dir($dir)) {
            return '';
        }
        $real = realpath($dir);
        if ($real === false || $real === '' || $real === '/') {
            return '';
        }
        // Never treat a directory that contains the document root (an ancestor) as cache.
        $docReal = ($documentRoot !== '') ? realpath($documentRoot) : false;
        if ($docReal !== false && $docReal !== '' && strpos($docReal . '/', $real . '/') === 0) {
            return '';
        }
        // Must actually contain a LiteSpeed cache file (magic-signature check).
        if (!self::hasCacheFile($real)) {
            return '';
        }
        return $real;
    }

    // True if the tree holds at least one file whose header magic matches.
    private static function hasCacheFile($root, $scanLimit = 500) {
        $seen = 0;
        try {
            $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));
            foreach ($it as $file) {
                if (!$file->isFile() || $file->isLink()) {
                    continue;
                }
                if (self::isMagic($file->getPathname())) {
                    return true;
                }
                if (++$seen >= $scanLimit) {
                    break;
                }
            }
        } catch (Exception $e) {
            return false;
        }
        return false;
    }

    private static function isMagic($file) {
        $fh = @fopen($file, 'rb');
        if (!$fh) {
            return false;
        }
        $head = fread($fh, strlen(self::MAGIC));
        fclose($fh);
        return $head === self::MAGIC;
    }

    // Count cache files under a validated root.
    public static function countFiles($root) {
        $stats = self::stats($root);
        return $stats['count'];
    }

    // Single-pass stats for a validated root: number of files and total bytes on disk.
    public static function stats($root) {
        $result = array('count' => 0, 'bytes' => 0);
        try {
            $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));
            foreach ($it as $file) {
                if ($file->isFile() && !$file->isLink()) {
                    $result['count']++;
                    $result['bytes'] += $file->getSize();
                }
            }
        } catch (Exception $e) {
        }
        return $result;
    }

    // Human-readable byte size (e.g. "3.2 MB").
    public static function humanBytes($bytes) {
        $bytes = (float)$bytes;
        $units = array('B', 'KB', 'MB', 'GB', 'TB');
        $i = 0;
        while ($bytes >= 1024 && $i < count($units) - 1) {
            $bytes /= 1024;
            $i++;
        }
        return round($bytes, ($i === 0) ? 0 : 1) . ' ' . $units[$i];
    }

    /*
     * Delete cache files. $days == 0 removes all, otherwise only files whose mtime
     * is older than $days days. Every file's realpath must stay inside $root.
     * Returns array('deleted' => int, 'kept' => int, 'errors' => int).
     */
    public static function purge($root, $days = 0) {
        $result = array('deleted' => 0, 'kept' => 0, 'errors' => 0);

        $root = realpath($root);
        if ($root === false || $root === '' || $root === '/') {
            return $result;
        }
        $prefix = $root . DIRECTORY_SEPARATOR;
        $cutoff = ($days > 0) ? (time() - ((int)$days * 86400)) : 0;

        try {
            $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));
            foreach ($it as $file) {
                if (!$file->isFile() || $file->isLink()) {
                    continue;
                }
                $real = realpath($file->getPathname());
                if ($real === false || strpos($real, $prefix) !== 0) {
                    $result['errors']++;
                    continue;
                }
                if ($cutoff > 0 && $file->getMTime() >= $cutoff) {
                    $result['kept']++;
                    continue;
                }
                if (@unlink($real)) {
                    $result['deleted']++;
                } else {
                    $result['errors']++;
                }
            }
        } catch (Exception $e) {
            $result['errors']++;
        }
        return $result;
    }

    // Parse a "CacheRoot <path>" directive from the site .htaccess (LiteSpeed Enterprise).
    private static function htaccessCacheRoot($documentRoot) {
        if ($documentRoot === '') {
            return '';
        }
        $htaccess = $documentRoot . '/.htaccess';
        if (!is_file($htaccess) || !is_readable($htaccess)) {
            return '';
        }
        $lines = @file($htaccess, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if (!$lines) {
            return '';
        }
        foreach ($lines as $line) {
            if (preg_match('/^\s*CacheRoot\s+(\S+)/i', $line, $m)) {
                $path = trim($m[1], "\"'");
                if ($path !== '' && $path[0] !== '/') {
                    $path = dirname($documentRoot) . '/' . $path; // relative to VH root
                }
                return $path;
            }
        }
        return '';
    }
}
