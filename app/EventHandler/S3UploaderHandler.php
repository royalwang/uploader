<?php
/**
 * Created by PhpStorm.
 * User: san
 * Date: 6/30/2015
 * Time: 4:36 PM
 */

namespace App\EventHandler {

    use Aws\Common\Enum\Region;
    use Aws\S3\S3Client;
    use Exception;
    use Minute\App\App;
    use Minute\Errors\LoginError;
    use Minute\Errors\UploadError;
    use Minute\Events\UploadEvent;
    use Minute\Utils\PathUtils;

    class S3UploaderHandler {
        public function upload(UploadEvent $event) {
            set_time_limit(max(100, ini_get('max_execution_time')));

            if (!$event->getUrl()) { #some other uploader has already taken care of it?
                if ($data = $event->getEventData()) {
                    if ($file = @$data['file']) {
                        if (file_exists($file)) {
                            $app     = App::getInstance();
                            $user_id = $event->getUserId();

                            if ($s3conf = $app->config->getKey('private/api_keys/aws-s3')) {
                                if (!empty($s3conf['access_key']) && !empty($s3conf['secret_key']) && !empty($s3conf['bucket_name'])) {
                                    if (($user_id > 0) || !empty($s3conf['anonymous_uploads'])) {
                                        try {
                                            $s3       = S3Client::factory(array('key' => $s3conf['access_key'], 'secret' => $s3conf['secret_key'],
                                                                                'region' => @$s3conf['region'] ?: Region::US_EAST_1));
                                            $type     = PathUtils::getFileType($file);
                                            $file_key = sprintf("users/%s/%s/%d-%s", $user_id > 0 ? $user_id : 'anon', $type, filesize($file), basename($file));

                                            $result = $s3->putObject(array(
                                                'Bucket' => $s3conf['bucket_name'],
                                                'SourceFile' => $file,
                                                'Key' => $file_key,
                                                'ContentType' => 'text/plain',
                                                'ACL' => 'public-read',
                                                'StorageClass' => 'REDUCED_REDUNDANCY'
                                            ));

                                            if (!empty($result['ObjectURL'])) {
                                                $url = $result['ObjectURL'];

                                                if (!empty($s3conf['cdn'])) {
                                                    $event->setUrl(sprintf('http://%s/%s', $s3conf['cdn'], $file_key));
                                                } else {
                                                    $event->setUrl($url);
                                                }
                                            } else {
                                                throw new UploadError("Error uploading to S3: No url returned");
                                            }
                                        } catch (Exception $e) {
                                            throw new UploadError("Error uploading to S3: " . $e->getMessage(), $e);
                                        }
                                    } else {
                                        throw new LoginError("You must be logged in to upload.", $user_id);
                                    }
                                } else {
                                    throw new UploadError("S3 configuration is incomplete.", $s3conf);
                                }
                            } else {
                                throw new UploadError("S3 configuration is empty");
                            }
                        } else {
                            throw new UploadError("Upload file is missing");
                        }
                    } else {
                        throw new UploadError("Upload data is empty");
                    }
                }
            }
        }
    }
}