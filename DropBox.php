<?php

class DropBox {

    static function Load() {
    }

    static function Info() {
        return array (
            'name'    => 'DropBox',
            'author'  => 'Alexandre Girard',
            'version' => '1.0.0',
            'site'    => 'http://alexgirard.com/',
            'notes'   => 'Convert video found inside a filesystem dropbox'
        );
    }

    static function Install() {
      Settings::Set ("dropbox_path", "/please/set/correct/path");
    }

    static function Uninstall() {
      Settings::Remove ("dropbox_path");
    }

    static function ConvertVideoFile($file, $category) {
      App::LoadClass ('Video');
      App::LoadClass ('Category');
      App::LoadClass ('Settings');

      Functions::RedirectIf ($logged_in = User::LoginCheck(), HOST . '/login/');
      $admin = new User ($logged_in);
      Functions::RedirectIf (User::CheckPermissions ('admin_panel', $admin), HOST . '/myaccount/');

      $dropbox_path = Settings::Get ('dropbox_path');

      $extension = Functions::GetExtension ($file);
      if (!preg_match ("/$extension/i", Functions::GetVideoTypes ('fileDesc'))) {
        throw new Exception (Language::GetText('error_uploadify_extension'));
      }

      $target = UPLOAD_PATH . '/temp/' . basename($file);
      if (copy($file, $target)) {
        unlink($file);
      }

      ### Change permissions on raw video file
      try {
          Filesystem::Open();
          Filesystem::SetPermissions ($target, 0644);
          Filesystem::Close();
      } catch (Exception $e) {
          App::Alert ('Error During Video Upload', $e->getMessage());
          throw new Exception (Language::GetText('error_uploadify_system', array ('host' => HOST)));
      }

      $cat_id = Category::Exist(array('slug'=>$category));
      if(!$cat_id) {
        $cat_id = Category::Create(array(
          'cat_name'  => ucwords($category),
          'slug'      => $category
        ));
      }

      $data = array();
      $data['title'] = htmlspecialchars (trim ( basename($file, $extension) ));
      $data['filename'] = basename($file);
      $data['gated'] = '0';
      $data['private'] = '0';
      $data['disable_embed'] = '0';
      $data['cat_id'] = '1';
      $data['tags'] = '';
      $data['user_id'] = $admin->user_id;
      $data['original_extension'] = $extension;
      $data['status'] = 'pending conversion';
      $data['cat_id'] = $cat_id;
      $id = Video::Create($data);

      error_log('video to convert : ' . $id);

      ### Initilize Encoder
      $converter_cmd = 'nohup ' . Settings::Get ('php') . ' ' . DOC_ROOT . '/cc-core/system/encode.php --video="' . $id . '" >> /tmp/converter.log &';
      error_log($converter_cmd);
      exec ($converter_cmd);
    }

    static function displayDropboxCategory($path) {
      App::LoadClass ('Settings');

      if (substr($path, -1) != '/') {
        $path .= '/';
      }

      $dropbox_path = Settings::Get ('dropbox_path');
      $allowed_extension = array('mov', 'avi', 'mp4');

      if($path == $dropbox_path) {
        $category = "general";
      } else {
        $category = basename($path);
      }

      //get all files in specified directory
      $files = glob($path . "*");

      if(sizeof($files) > 0) {

?>
<div class="block list">
<h3>Category : <?= ucwords($category) ?> - <?= sizeof($files) ?> videos</h3>
  <form method="post">
    <input type="hidden" name="dropbox_import" value="true" />
    <input type="hidden" name="dropbox_file" value="all" />
    <input type="hidden" name="dropbox_import_path" value="<?=$path ?>" />
    <input type="hidden" name="dropbox_category" value="<?= $category ?>" />
    <input class="button" type="submit" value="Import All" style="float: right;right: 20px;position: relative;"/>
  </form>
  <table>
    <thead>
      <tr>
        <td class="large">Filename</td>
        <td class="large">Action</td>
      </tr>
    </thead>
    <tbody>
  <?php
  //print each file name
  foreach($files as $file) {
    //check to see if the file is a folder/directory
    if(!is_dir($file)) {
      $extension = pathinfo($file, PATHINFO_EXTENSION);
      if (in_array($extension, $allowed_extension)) {
        $odd = empty ($odd) ? true : false;
  ?>
    <tr class="<?=$odd ? 'odd' : ''?>">
      <td class="video-title"><?= basename($file) ?></td>
      <td>
        <form method="post">
          <input type="hidden" name="dropbox_import" value="true" />
          <input type="hidden" name="dropbox_file" value="<?= $file ?>" />
          <input type="hidden" name="dropbox_category" value="<?= $category ?>" />
          <input class="button" type="submit" value="Import" />
        </form>
      </td>
    </tr>
  <?php
      }
    }
  }
  ?>
    </tbody>
  </table>
</div>
<?php
      }
    }


    static function Settings() {
      App::LoadClass ('Settings');

      $dropbox_path = Settings::Get ('dropbox_path');
      $message = null;

      if (isset ($_POST['dropbox_settings']) ) {

        // Set dropbox path
        if (!empty($_POST['dropbox_path']) && !ctype_space ($_POST['dropbox_path'])) {
          $dropbox_path = $_POST['dropbox_path'];
          if (substr($dropbox_path, -1) != '/') {
            $dropbox_path .= '/';
          }
          Settings::Set('dropbox_path', $dropbox_path);
          $message = 'Settings have been updated.';
          $message_type = 'success';
        }

      }

      if (isset ($_POST['dropbox_import']) ) {

        if (!empty($_POST['dropbox_file']) && !ctype_space ($_POST['dropbox_file'])) {

          $dropbox_file = $_POST['dropbox_file'];
          $category = $_POST['dropbox_category'];

          if($dropbox_file == 'all') {
            $dropbox_import_path = $_POST['dropbox_import_path'];
            if($handle = opendir($dropbox_import_path)) {
              while (false !== ($entry = readdir($handle))) {
                $extension = pathinfo($dropbox_import_path . $entry, PATHINFO_EXTENSION);
                if ($entry != "." && $entry != ".." && in_array($extension, $allowed_extension)) {
                  DropBox::ConvertVideoFile($entry, $category);
                }
              }
            }
          } else {
            if(file_exists($dropbox_file)) {
              DropBox::ConvertVideoFile($dropbox_file, $category);
              $message = 'Video <em>"'. $dropbox_file.'"</em> have been imported.';
              $message_type = 'success';
            } else {
              $message = 'File <em>"'. $dropbox_file .'"</em> does not exist.';
              $message_type = 'error';
            }
          }
        }

      }

?>
<h1>DropBox Plugin</h1>

    <?php if ($message): ?>
    <div class="<?=$message_type?>"><?=$message?></div>
    <?php endif; ?>

<div class="block">

  <form method="post">
    <div class="row">
      <label>Dropbox path :</label>
      <input type="text" name="dropbox_path" value="<?php echo $dropbox_path; ?>" class="text"/>
    </div>
    <div class="row-shift">
      <input type="hidden" name="dropbox_settings" value="true" />
      <input class="button" type="submit" value="Update Plugin Settings" />
    </div>
  </form>

</div>

<?php

    if($handle = opendir($dropbox_path)) {

      DropBox::displayDropboxCategory($dropbox_path);

      //get all files in specified directory
      $files = glob($dropbox_path . "*");

      //print each file name
      foreach($files as $file) {
        //check to see if the file is a folder/directory
        if(is_dir($file)) {
          DropBox::displayDropboxCategory($file);
        }
      }

    } else {

?>
<div class="block">
<p>Please set a path to display available video in dropbox.</p>
</div>
<?php }

  }
}
