<?php
/**
 * Created by PhpStorm.
 * User: san
 * Date: 9/19/2015
 * Time: 2:58 PM
 */

namespace App\Controllers\Uploader {

    use Minute\Core\Singleton;
    use Minute\Utils\HttpUtils;
    use Minute\Utils\PathUtils;

    class HttpProxy extends Singleton {
        /**
         * @return HttpProxy
         */
        public static function getInstance() {
            return parent::getInstance();
        }

        /**
         * @param $urls
         *
         * @return array|null
         */
        public function proxy($urls) {
            if (!empty($urls)) {
                foreach ($urls as $url) {
                    $tmp  = PathUtils::getTmpDir('tmp/proxy');
                    $path = sprintf("%s/%s.%s", $tmp, md5($url), pathinfo($url, PATHINFO_EXTENSION));

                    if ($file = file_exists($path) ? $path : HttpUtils::downloadFile($url, $path)) {
                        $files[] = ['remote' => $url, 'local' => $file];
                    }
                }
            }

            return !empty($files) ? $files : null;
        }
    }
}