<?php

namespace App\Controllers\Uploader {
    use Aws\Common\Enum\Region;
    use Aws\S3\S3Client;
    use Exception;
    use Minute\App\App;
    use Minute\Errors\LoginError;
    use Minute\Errors\UploadError;
    use Minute\Events\LoginEvent;
    use Minute\Events\UploadEvent;
    use Minute\Http\HttpResponse;
    use Minute\Session\SessionManager;
    use Minute\Utils\PathUtils;

    class Uploader {

        public function upload() {
            $app     = App::getInstance();
            $user_id = SessionManager::getInstance()->getUserID();

            if (!empty($_FILES['file']['name'])) {
                $filename = $_FILES['file']['name'];
                $file     = sprintf("%s/%s", PathUtils::getTmpDir('tmp/uploads'), $filename);
                $saved    = move_uploaded_file($_FILES['file']['tmp_name'], $file);
            } elseif (!empty($_POST['file']) && !empty($_POST['data'])) {
                $filename = $_POST['file'];
                $file     = sprintf("%s/%s", PathUtils::getTmpDir('tmp/uploads'), $filename);
                $data     = !empty($_POST['base64']) ? base64_decode($_POST['data']) : $_POST['data'];
                $saved    = file_put_contents($file, $data);
            }

            if (!empty($file) & !empty($saved)) {
                try {
                    $type  = PathUtils::getFileType($file);
                    $event = new UploadEvent($user_id, ['file' => $file]);

                    if ($app->dispatch("user_upload_$type", $event)) {
                        if ($upload = $event->getURL()) {
                            HttpResponse::getInstance()->display(['url' => $upload], '', true);
                        }
                    }
                } finally {
                    @unlink($file);
                }
            }

            HttpResponse::getInstance()->displayError("Unable to upload file");
        }
    }
}