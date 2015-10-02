<?php

namespace App\Controllers\Uploader {

    use Minute\App\App;
    use Minute\Events\UploadEvent;
    use Minute\Http\HttpResponse;
    use Minute\Session\SessionManager;
    use Minute\Utils\HttpUtils;
    use Minute\Utils\PathUtils;

    class UrlProxy {

        public function index($urls) {
            if ($files = HttpProxy::getInstance()->proxy($urls)) {
                $app     = App::getInstance();
                $user_id = SessionManager::getInstance()->getUserID();

                foreach ($files as $file) {
                    $event = new UploadEvent($user_id, ['file' => $file['local']]);

                    if ($app->dispatch(UploadEvent::USER_UPLOAD_IMAGE, $event)) {
                        if ($upload = $event->getURL()) {
                            $results[] = ['src' => $file['remote'], 'copy' => $upload];
                        }
                    }
                }
            }

            HttpResponse::getInstance()->display(!empty($results) ? $results : '');
        }
    }
}