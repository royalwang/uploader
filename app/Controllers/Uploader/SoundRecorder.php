<?php

namespace App\Controllers\Uploader {
    use Minute\App\App;
    use Minute\Http\HttpResponse;
    use Minute\Utils\PathUtils;

    class SoundRecorder {

        public function save($fn) {
            $app     = App::getInstance();
            $content = file_get_contents('php://input');
            //$file_path = sprintf("%s/%s.wav", PathUtils::getTmpDir('tmp/recordings'), $fn);
            $wav = sprintf("E:/var/Dropbox/www/viralvideorobot/public/tmp/%s.wav", $fn);

            if (file_put_contents($wav, $content)) {
                $mp3 = preg_replace('/(\.wav)$/', '.mp3', $wav);
                system(sprintf('ffmpeg -y -i "%s" -qscale:a 2 "%s"', $wav, $mp3));

                if (file_exists($mp3)) {
                    $output = basename($mp3);
                    unlink($wav);
                } else {
                    $output = basename($wav);
                }

                echo json_encode(['filename' => "/tmp/$output"]);
                exit();
            }

            HttpResponse::getInstance()->displayError("Content not found");
        }
    }
}