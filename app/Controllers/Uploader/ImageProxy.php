<?php

namespace App\Controllers\Uploader {

    use Minute\App\App;
    use Minute\Events\UploadEvent;
    use Minute\Http\HttpResponse;
    use Minute\Session\SessionManager;
    use Minute\Utils\HttpUtils;
    use Minute\Utils\PathUtils;

    class ImageProxy {

        public function index($urls, $width, $height) {
            if ($images = HttpProxy::getInstance()->proxy($urls)) {
                $app     = App::getInstance();
                $user_id = SessionManager::getInstance()->getUserID();

                foreach ($images as $image) {
                    $resized = self::resize_image($image['local'], $width ?: 854, $height ?: 480);
                    $event   = new UploadEvent($user_id, ['file' => $resized]);

                    if ($app->dispatch(UploadEvent::USER_UPLOAD_IMAGE, $event)) {
                        if ($upload = $event->getURL()) {
                            $results[] = ['src' => $image['remote'], 'copy' => $upload];
                        }
                    }
                }
            }

            HttpResponse::getInstance()->display(!empty($results) ? $results : '');
        }

        protected function resize_image($image, $width, $height) {
            $ext = strtolower(pathinfo($image, PATHINFO_EXTENSION));

            if (!preg_match('/(jpg|jpeg|png|gif)/', $ext)) {
                $src = imagecreatefromstring(file_get_contents($image));
                unlink($image);

                $image = sprintf("%s/%s.png", pathinfo($image, PATHINFO_DIRNAME), pathinfo($image, PATHINFO_FILENAME));

                imagepng($src, $image);
                imagedestroy($src);
            }

            list($oWidth, $oHeight) = getimagesize($image);

            if (($oWidth > $width) || ($oHeight > $height)) {
                $ratio = min($width / $oWidth, $height / $oHeight);
                $src   = imagecreatefromstring(file_get_contents($image));
                $dst   = imagescale($src, $oWidth * $ratio, $oHeight * $ratio, IMG_BICUBIC);

                call_user_func(preg_match('/jpeg|jpg/', $ext) ? 'imagejpeg' : (preg_match('/gif/', $ext) ? 'imagegif' : 'imagepng'), $dst, $image);

                imagedestroy($src);
                imagedestroy($dst);
            }

            return $image;
        }
    }
}