
<?php echo $header; ?><?php echo $column_left; ?>
<div id="content">
  <div class="page-header">
    <div class="container-fluid">
      <div class="pull-right">
        <!-- Form submit button -->
        <a href="<?php echo $purgeAll ; ?>" data-toggle="tooltip" title="<?php echo $button_purgeAll ; ?>" class="btn btn-warning"><i class="fa fa-trash"></i></a>
        <a href="<?php echo $recacheAll ; ?>" data-toggle="tooltip" title="<?php echo $button_recacheAll ; ?>" class="btn btn-success"><i class="fa fa-flash"></i></a>
        &nbsp;&nbsp;
        
        <button type="submit" form="form-first-module" data-toggle="tooltip" title="<?php echo $button_save ; ?>" class="btn btn-primary"><i class="fa fa-save"></i></button>
        <!-- Back button -->
        <a href="<?php echo $cancel ; ?>" data-toggle="tooltip" title="<?php echo $button_cancel ; ?>" class="btn btn-default"><i class="fa fa-reply"></i></a></div>
      <!-- Heading is mentioned here -->
      <h1><?php echo $heading_title ; ?></h1>
      <!-- Breadcrumbs are listed here -->
      <ul class="breadcrumb">
        <?php foreach($breadcrumbs as $breadcrumb) { ?>
        <li><a href="<?php echo $breadcrumb['href'] ; ?>"><?php echo $breadcrumb['text'] ; ?></a></li>
        <?php } ?>
      </ul>
    </div>
  </div>
  <div class="container-fluid">
      <div class="panel panel-default">
      <div class="panel-heading">
        <h3 class="panel-title"><i class="fa fa-pencil"></i> <?php echo $text_edit ; ?></h3>
      </div>
      <div class="panel-body">
        <?php if($error_warning) { ?>
        <div class="alert alert-danger alert-dismissible"><i class="fa fa-exclamation-circle"></i> <?php echo $error_warning ; ?>
          <button type="button" class="close" data-dismiss="alert">&times;</button>
        </div>
        <?php } ?>
        <?php if($success) { ?>
        <div class="alert alert-success alert-dismissible"><i class="fa fa-check-circle"></i> <?php echo $success ; ?>
          <button type="button" class="close" data-dismiss="alert">&times;</button>
        </div>
        <?php } ?>          
        <!-- form starts here -->
        <form action="<?php echo $self ; ?>" method="post" enctype="multipart/form-data" id="form-lscache-module" class="form-horizontal">
          <ul class="nav nav-tabs">
            <li <?php echo $tabtool->check($tab, 'general', 'class'); ?>><a href="#tab-general" data-toggle="tab"><?php echo $tab_general ; ?></a></li>
            <li <?php echo $tabtool->check($tab, 'pages', 'class'); ?>><a href="#tab-pages" data-toggle="tab"><?php echo $tab_pages ; ?></a></li>
            <li <?php echo $tabtool->check($tab, 'modules', 'class'); ?>><a href="#tab-modules" data-toggle="tab"><?php echo $tab_modules ; ?></a></li>
          </ul>
          <div class="tab-content">
            <div class="tab-pane <?php echo $tabtool->check($tab,'general'); ?>" id="tab-general">
                <div class="form-group">
                  <label class="col-sm-2 control-label" for="module_lscache_status"><?php echo $entry_status ; ?></label>
                  <div class="col-sm-10">
                    <select name="module_lscache_status" id="input-status" class="form-control">
                      <option value="1" <?php echo $selectEnable->check($module_lscache_status, '1') ; ?>><?php echo $text_enabled ; ?></option>
                      <option value="0" <?php echo $selectEnable->check($module_lscache_status, '0') ; ?>><?php echo $text_disabled ; ?></option>
                    </select>
                  </div>
                </div>
                
                <div class="form-group">
                  <label class="col-sm-2 control-label" for="module_lscache_public_ttl"><span data-toggle="tooltip" title="<?php echo $help_public_ttl ; ?>"><?php echo $entry_public_ttl ; ?></span></label>
                  <div class="col-sm-10">
                    <input type="text" name="module_lscache_public_ttl" value="<?php echo $module_lscache_public_ttl ; ?>" placeholder="<?php echo $entry_public_ttl ; ?>" id="input-total" class="form-control" />
                  </div>
                </div>

                <div class="form-group">
                  <label class="col-sm-2 control-label" for="module_lscache_esi"><?php echo $entry_esi ; ?></label>
                  <div class="col-sm-10">
                    <select name="module_lscache_esi" id="input-status" class="form-control">
                <?php if(($serverType == 'LITESPEED_SERVER_ADC') || ($serverType == 'LITESPEED_SERVER_ENT'))  { ?>
                      <option value="1" <?php echo $selectEnable->check($module_lscache_esi, '1') ; ?>><?php echo $text_enabled ; ?></option>
                <?php } ?>
                      <option value="0" <?php echo $selectEnable->check($module_lscache_esi, '0') ; ?>><?php echo $text_disabled ; ?></option>
                    </select>
                  </div>
                </div>

                <div class="form-group">
                  <label class="col-sm-2 control-label" for="module_lscache_vary_login"><span data-toggle="tooltip" title="<?php echo $help_vary_login ; ?>"><?php echo $entry_vary_login ; ?></span></label>
                  <div class="col-sm-10">
                    <select name="module_lscache_vary_login" id="input-status" class="form-control">
                      <option value="1" <?php echo $selectEnable->check($module_lscache_vary_login, '1') ; ?>><?php echo $text_enabled ; ?></option>
                      <option value="0" <?php echo $selectEnable->check($module_lscache_vary_login, '0') ; ?>><?php echo $text_disabled ; ?></option>
                    </select>
                  </div>
                </div>
                    
                <div class="form-group">
                  <label class="col-sm-2 control-label" for="module_lscache_recache_option"><?php echo $entry_recache_option ; ?></label>
                  <div class="col-sm-10">
                    <select name="module_lscache_recache_option" id="input-status" class="form-control">
                        <option value="0" <?php echo $selectDisable->check($module_lscache_recache_option, '0') ; ?>><?php echo $text_recache_default ; ?></option>
                      <option value="1" <?php echo $selectDisable->check($module_lscache_recache_option, '1') ; ?>><?php echo $text_recache_language ; ?></option>
                      <option value="2" <?php echo $selectDisable->check($module_lscache_recache_option, '2') ; ?>><?php echo $text_recache_currency ; ?></option>
                      <option value="3" <?php echo $selectDisable->check($module_lscache_recache_option, '3') ; ?>><?php echo $text_recache_combination ; ?></option>
                    </select>
                  </div>
                </div>                    

                <div class="form-group">
                  <label class="col-sm-2 control-label" for="module_lscache_ajax_wishlist"><?php echo $entry_ajax_wishlist ; ?></label>
                  <div class="col-sm-10">
                    <select name="module_lscache_ajax_wishlist" id="input-status" class="form-control">
                      <option value="1" <?php echo $selectEnable->check($module_lscache_ajax_wishlist, '1') ; ?>><?php echo $text_enabled ; ?></option>
                      <option value="0" <?php echo $selectEnable->check($module_lscache_ajax_wishlist, '0') ; ?>><?php echo $text_disabled ; ?></option>
                    </select>
                  </div>
                </div>
                
                <div class="form-group">
                  <label class="col-sm-2 control-label" for="module_lscache_ajax_compare"><?php echo $entry_ajax_compare ; ?></label>
                  <div class="col-sm-10">
                    <select name="module_lscache_ajax_compare" id="input-status" class="form-control">
                      <option value="1" <?php echo $selectDisable->check($module_lscache_ajax_compare, '1') ; ?>><?php echo $text_enabled ; ?></option>
                      <option value="0" <?php echo $selectDisable->check($module_lscache_ajax_compare, '0') ; ?>><?php echo $text_disabled ; ?></option>
                    </select>
                  </div>
                </div>

                <div class="form-group">
                  <label class="col-sm-2 control-label" for="module_lscache_log_level"><?php echo $entry_loglevel ; ?></label>
                  <div class="col-sm-10">
                    <select name="module_lscache_log_level" id="input-status" class="form-control">
                      <option value="0" <?php echo $selectDisable->check($module_lscache_log_level, '0') ; ?>><?php echo $text_disabled ; ?></option>
                      <option value="3" <?php echo $selectDisable->check($module_lscache_log_level, '3') ; ?>><?php echo $text_error ; ?></option>
                      <option value="6" <?php echo $selectDisable->check($module_lscache_log_level, '6') ; ?>><?php echo $text_info ; ?></option>
                      <option value="8" <?php echo $selectDisable->check($module_lscache_log_level, '8') ; ?>><?php echo $text_debug ; ?></option>
                    </select>
                  </div>
                </div>
                                    
                
            </div>
                    
            <div class="tab-pane <?php echo $tabtool->check($tab,'pages'); ?>" id="tab-pages">

                <div class="table-responsive">
                    <table class="table table-bordered table-hover">
                      <thead>
                        <tr>
                          <td class="text-left"><?php echo $page_name ; ?></td>
                          <td class="text-left"><?php echo $page_route ; ?></td>
                          <td class="text-left"><span data-toggle="tooltip" title="<?php echo $help_page_logout_cachable ; ?>"><?php echo $page_cachable ; ?></span></td>
                          <td class="text-left"><span data-toggle="tooltip" title="<?php echo $help_page_login_cachable ; ?>"><?php echo $login_cachable ; ?></span></td>
                          <td class="text-right">
                              <span style="display:inline-block;"><?php echo $text_action ; ?></span>
                              <span style="min-width:50%;display:inline-block;">
                                  <a href="<?php echo $addPage ; ?>" data-toggle="tooltip" title="<?php echo $button_add; ?>" class="btn btn-success"><i class="fa fa-plus-circle"></i></a>
                              </span>
                          </td>
                        </tr>
                      </thead>
                    
                      <tbody>
                <?php if($action == 'addPage') { ?>
                        <tr>
                          <td class="text-left"><input type="text" name="page_add-name" value="" placeholder="Page Name"  class="form-control"></td>
                          <td class="text-left"><input type="text" name="page_add-route" value="" placeholder="Page Route"  class="form-control"></td>
                          <td class="text-left"><input type="checkbox" name="page_add-cacheLogout" value="1" data-toggle="toggle"></td>
                          <td class="text-left"><input type="checkbox" name="page_add-cacheLogin" value="1" data-toggle="toggle"></td>
                          <td class="text-right">
                            <a href="<?php echo $deletePage ; ?> " data-toggle="tooltip" title="<?php echo $button_deletePage ; ?>" class="btn btn-danger"><i class="fa fa-minus-circle"></i></a>
                          </td>
                        </tr>
                <?php } ?>

                <?php foreach ($pages as $page) { ?>
                        <tr>
                          <td class="text-left"> <?php echo $page['name'] ; ?></td>
                          <td class="text-left"> <?php echo $page['route'] ; ?></td>
                          <td class="text-left"><input type="checkbox" name="<?php echo $page['key']; ?>-cacheLogout" value="1" <?php echo $checkDisable->check($page['cacheLogout']) ; ?> data-toggle="toggle" ></td>
                          <td class="text-left"><input type="checkbox" name="<?php echo $page['key']; ?>-cacheLogin" value="1"  <?php echo $checkDisable->check($page['cacheLogin']) ; ?>  data-toggle="toggle" ></td>
                          <td class="text-right">
                          <?php if(($page['cacheLogout'] == '1')  or ($page['cacheLogin'] == '1')) { ?>
                            <a href="<?php echo $purgePage ; ?>&key=<?php echo $page['key']; ?>" data-toggle="tooltip" title="<?php echo $button_purgePage ; ?>" class="btn btn-warning"><i class="fa fa-trash"></i></a>
                          <?php } ?>
                          
                          <?php if($page['default'] != '1') { ?>
                            <a href="<?php echo $deletePage ; ?>&key=<?php echo $page['key']; ?>" data-toggle="tooltip" title="<?php echo $button_deletePage ; ?>" class="btn btn-danger"><i class="fa fa-minus-circle"></i></a>
                          <?php } ?>
                            
                          </td>
                        </tr>
                <?php } ?>
                
                      </tbody>
                    </table>

                </div>
            </div>

            <div class="tab-pane <?php echo $tabtool->check($tab, 'modules'); ?>" id="tab-modules">
                <?php if($serverType == 'LITESPEED_SERVER_OLS') { ?>
                <div class="alert alert-danger alert-dismissible"><i class="fa fa-exclamation-circle"></i> <?php echo $esi_not_support ; ?>
                  <button type="button" class="close" data-dismiss="alert">&times;</button>
                </div>
                <?php } ?>
                <div class="table-responsive">
                    <table class="table table-bordered table-hover">
                      <thead>
                        <tr>
                          <td class="text-left"><?php echo $esi_name ; ?></td>
                          <td class="text-left"><?php echo $esi_route ; ?></td>
                          <td class="text-left"><?php echo $esi_type ; ?></td>
                          <td class="text-left"><span data-toggle="tooltip" title="<?php echo $help_esi_ttl ; ?>"><?php echo $esi_ttl ; ?></span></td>
                          <td class="text-left"><span data-toggle="tooltip" title="<?php echo $help_esi_tag ; ?>"><?php echo $esi_tag ; ?></span></td>
                          <td class="text-right">
                              <span style="display:inline-block;"><?php echo $text_action ; ?></span>
                              <span style="min-width:45%;display:inline-block;">
                                  <a href="<?php echo $addESIModule ; ?>" data-toggle="tooltip" title="<?php echo $button_addModule; ?>" class="btn btn-success"><i class="fa fa-plus-circle"></i></a> 
                                  <a href="<?php echo $addESIRoute ; ?>" data-toggle="tooltip" title="<?php echo $button_addRoute; ?>" class="btn btn-success"><i class="fa fa-plus"></i></a>
                              </span>
                          </td>
                        </tr>
                      </thead>
                      <tbody>

                <?php if($action == 'addESIModule') { ?>
                        <tr>
                          <td class="text-left" colspan="2">
                            <select name="esi_add-module" class="form-control">
                              <?php foreach($moduleOptions as $module) { ?>
                              <option value="<?php echo $module['module_id'] ; ?>" ><?php echo $module['code'] ; ?> : <?php echo $module['name'] ; ?> &nbsp;&nbsp;&nbsp;&nbsp; &nbsp;&nbsp;&nbsp;&nbsp; extension/module/<?php echo $module['code'] ; ?> </option>
                              <?php } ?>
                              <?php foreach($extensionOptions as $module) { ?>
                              <option value="<?php echo $module['code'] ; ?>" ><?php echo $module['code'] ; ?>&nbsp;&nbsp;&nbsp;&nbsp; &nbsp;&nbsp;&nbsp;&nbsp;  extension/module/<?php echo $module['code'] ; ?> </option>
                              <?php } ?>
                            </select>
                          </td>
                <?php } ?>

                <?php if($action == 'addESIRoute') { ?>
                        <tr>
                          <td class="text-left"><input type="text" name="esi_add-name" value="" placeholder="ESI Module Name"  class="form-control"></td>
                          <td class="text-left"><input type="text" name="esi_add-route" value="" placeholder="ESI Module Route"  class="form-control"></td>
                <?php } ?>
                        
                <?php if(($action == 'addESIModule') || ($action == 'addESIRoute'))  { ?>

                          <td class="text-left">
                            <select name="esi_add-esi_type" class="form-control">
                              <option value="3" selected><?php echo $esi_public ; ?></option>
                              <option value="2" ><?php echo $esi_private ; ?></option>
                              <option value="1" ><?php echo $esi_none ; ?></option>
                              <option value="0" ><?php echo $esi_disabled ; ?></option>
                            </select>
                          </td>
                          <td class="text-left"><input type="text" name="esi_add-esi_ttl" value="1800" placeholder="ESI Module TTS (seconds)"  class="form-control"></td>
                          <td class="text-left">
                            <select name="esi_add-esi_tag" id="input-status" class="form-control">
                              <option value="" selected><?php echo $text_default ; ?></option>
                              <option value="esi_cart">esi_cart</option>
                              <option value="esi_wishlist">esi_wishlist</option>
                              <option value="esi_compare">esi_compare</option>
                            </select>
                          </td>
                          <td class="text-right">
                            <a href="<?php echo $deleteESI ; ?> " data-toggle="tooltip" title="<?php echo $button_deleteModule ; ?>" class="btn btn-danger"><i class="fa fa-minus-circle"></i></a>
                          </td>
                        </tr>
                <?php } ?>

                <?php foreach ($modules as $module) { ?>
                        <tr>
                          <td class="text-left"><?php echo $module['name'] ; ?></td>
                          <td class="text-left"><?php echo $module['route'] ; ?></td>
                          <td class="text-left">
                            <select name="<?php echo $module['key'] ; ?>-esi_type" class="form-control">
                              <option value="3" <?php echo $selectDisable->check($module['esi_type'], '3') ; ?>><?php echo $esi_public ; ?></option>
                              <option value="2" <?php echo $selectDisable->check($module['esi_type'], '2') ; ?>><?php echo $esi_private ; ?></option>
                              <option value="1" <?php echo $selectDisable->check($module['esi_type'], '1') ; ?>><?php echo $esi_none ; ?></option>
                              <option value="0" <?php echo $selectDisable->check($module['esi_type'], '0') ; ?>><?php echo $esi_disabled ; ?></option>
                            </select>
                          </td>
                          <td class="text-left"><input type="text" name="<?php echo $module['key']; ?>-esi_ttl" value="<?php echo $module['esi_ttl']; ?>" placeholder="<?php echo $esi_ttl; ?>"  class="form-control"></td>
                          <td class="text-left">
                            <select name="<?php echo $module['key'] ; ?>-esi_tag" id="input-status" class="form-control">
                              <option value="" <?php echo $selectDefault->check($module['esi_tag'], '') ; ?>><?php echo $text_default ; ?></option>
                              <option value="esi_cart" <?php echo $selectDefault->check($module['esi_tag'], 'esi_cart') ; ?>>esi_cart</option>
                              <option value="esi_wishlist" <?php echo $selectDefault->check($module['esi_type'], 'esi_wishlist') ; ?>>esi_wishlist</option>
                            </select>
                          </td>
                          
                          <td class="text-right">
                          <?php if($module['esi_type'] >= '2') { ?>
                            <a href="<?php echo $purgeESI ; ?>&key=<?php echo $module['key']; ?>" data-toggle="tooltip" title="<?php echo $button_purgeESI ; ?>" class="btn btn-warning"><i class="fa fa-trash"></i></a>
                          <?php } ?>

                          <?php if((!isset($module['default'])) || ($module['default'] != '1')) { ?>
                          <a href="<?php echo $deleteESI ; ?>&key=<?php echo $module['key']; ?>" data-toggle="tooltip" title="<?php echo $button_deleteModule ; ?>" class="btn btn-danger"><i class="fa fa-minus-circle"></i></a>
                          <?php } ?>
                          
                          </td>
                        </tr>
                <?php } ?>
                
                      </tbody>
                    </table>
                </div>
            </div>
                    
          </div>
        </form>
      </div>
    </div>
  </div>
</div>
<?php echo $footer; ?>