<?php
/**
 * Created by PhpStorm.
 * User: san
 * Date: 6/30/2015
 * Time: 4:36 PM
 */

namespace App\EventHandler {
    use Exception;
    use Google_Client;
    use Google_Http_MediaFileUpload;
    use Google_Service_Exception;
    use Google_Service_YouTube;
    use Google_Service_YouTube_Video;
    use Google_Service_YouTube_VideoSnippet;
    use Google_Service_YouTube_VideoStatus;
    use Minute\App\App;
    use Minute\Errors\LoginError;
    use Minute\Errors\UploadError;
    use Minute\Events\UploadEvent;
    use Minute\User\UserManager;

    class YouTubeUploaderHandler {
        public function upload(UploadEvent $event) {
            set_time_limit(max(100, ini_get('max_execution_time')));

            if (!$event->getUrl()) { #some other uploader has already taken care of it?
                if ($data = $event->getEventData()) {
                    if ($file = @$data['file']) {
                        if (file_exists($file)) {
                            $app     = App::getInstance();
                            $user_id = $event->getUserId();

                            if ($youtubeConfig = $app->config->getKey('private/api_keys/youtube')) {
                                if (!empty($youtubeConfig['token'])) {
                                    if (($user_id > 0) || !empty($youtubeConfig['anonymous_uploads'])) {
                                        try {
                                            if ($userToken = UserManager::getInstance()->getUserData($user_id, 'youtube')) {
                                                try {
                                                    $uploader = 'local';
                                                    $url      = $this->uploadVideo($file, $userToken, $refreshToken);
                                                } catch (Exception $e) {
                                                }
                                            }

                                            if (empty($url)) {
                                                $uploader = 'global';
                                                $url      = $this->uploadVideo($file, $youtubeConfig['token'], $refreshToken);
                                            }

                                            if (!empty($refreshToken) && !empty($uploader)) {
                                                if ($uploader === 'global') {
                                                    $app->config->setKey('private/api_keys/youtube/token', $refreshToken);
                                                } elseif ($uploader === 'local') {
                                                    UserManager::getInstance()->setUserData($user_id, ['youtube' => $refreshToken]);
                                                }
                                            }

                                            if (!empty($url)) {
                                                $event->setUrl($url);
                                            } else {
                                                throw new UploadError("Error uploading video: No url returned");
                                            }
                                        } catch (Exception $e) {
                                            throw new UploadError("Error uploading video: " . $e->getMessage(), $e);
                                        }
                                    } else {
                                        throw new LoginError("You must be logged in to upload.", $user_id);
                                    }
                                } else {
                                    throw new UploadError("Youtube configuration is incomplete.", $youtubeConfig);
                                }
                            } else {
                                throw new UploadError("Youtube configuration is empty");
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

        private function uploadVideo($file, $token, &$refreshToken, $videoPrivacy = 'unlisted', $videoTitle = '', $videoDescription = '', $videoCategory = '', $videoTags = []) {
            $app              = App::getInstance();
            $youtubeConfig    = $app->config->getKey('private/api_keys/youtube');
            $application_name = $youtubeConfig['application_name'];
            $client_secret    = $youtubeConfig['client_secret'];
            $client_id        = $youtubeConfig['client_id'];
            $scope            = array('https://www.googleapis.com/auth/youtube.upload', 'https://www.googleapis.com/auth/youtube', 'https://www.googleapis.com/auth/youtubepartner');

            $videoPath        = $file;
            $videoTitle       = $videoTitle ?: basename($file);
            $videoDescription = $videoDescription ?: "Private video";
            $videoCategory    = $videoCategory ?: "22";
            $videoTags        = !empty($videoTags) ? $videoTags : array("youtube", "video", basename($file));

            try {
                // Client init
                $client = new Google_Client();
                $client->setApplicationName($application_name);
                $client->setClientId($client_id);
                $client->setAccessType('offline');
                $client->setAccessToken($token);
                $client->setScopes($scope);
                $client->setClientSecret($client_secret);

                if ($client->getAccessToken()) {
                    /**
                     * Check to see if our access token has expired. If so, get a new one and save it to file for future use.
                     */
                    if ($client->isAccessTokenExpired()) {
                        $newToken = json_decode($client->getAccessToken());
                        $client->refreshToken($newToken->refresh_token);
                        $refreshToken = $client->getAccessToken();
                    }

                    $youtube = new Google_Service_YouTube($client);

                    // Create a snipet with title, description, tags and category id
                    $snippet = new Google_Service_YouTube_VideoSnippet();
                    $snippet->setTitle($videoTitle);
                    $snippet->setDescription($videoDescription);
                    $snippet->setCategoryId($videoCategory);
                    $snippet->setTags($videoTags);

                    // Create a video status with privacy status. Options are "public", "private" and "unlisted".
                    $status = new Google_Service_YouTube_VideoStatus();
                    $status->setPrivacyStatus($videoPrivacy);

                    // Create a YouTube video with snippet and status
                    $video = new Google_Service_YouTube_Video();
                    $video->setSnippet($snippet);
                    $video->setStatus($status);

                    // Size of each chunk of data in bytes. Setting it higher leads faster upload (less chunks,
                    // for reliable connections). Setting it lower leads better recovery (fine-grained chunks)
                    $chunkSizeBytes = 1 * 1024 * 1024;

                    // Setting the defer flag to true tells the client to return a request which can be called
                    // with ->execute(); instead of making the API call immediately.
                    $client->setDefer(true);

                    // Create a request for the API's videos.insert method to create and upload the video.
                    $insertRequest = $youtube->videos->insert("status,snippet", $video);

                    // Create a MediaFileUpload object for resumable uploads.
                    $media = new Google_Http_MediaFileUpload(
                        $client,
                        $insertRequest,
                        'video/*',
                        null,
                        true,
                        $chunkSizeBytes
                    );
                    $media->setFileSize(filesize($videoPath));

                    // Read the media file and upload it chunk by chunk.
                    $status = false;
                    $handle = fopen($videoPath, "rb");
                    while (!$status && !feof($handle)) {
                        $chunk  = fread($handle, $chunkSizeBytes);
                        $status = $media->nextChunk($chunk);
                    }

                    fclose($handle);

                    // If you want to make other calls after the file upload, set setDefer back to false
                    $client->setDefer(false);

                    /**
                     * Video has successfully been upload, now lets perform some cleanup functions for this video
                     */
                    if (!empty($status->status) && ($status->status['uploadStatus'] == 'uploaded')) {
                        return sprintf("http://www.youtube.com/watch?v=%s", $status['id']);
                    }
                } else {
                    throw new UploadError("Problems uploading video on YouTube");
                }

            } catch (Google_Service_Exception $e) {
                throw new UploadError("Youtube error: " . $e->getMessage());
            } catch (Exception $e) {
                throw new UploadError("Upload error: " . $e->getMessage());
            }

            return null;
        }
    }
}