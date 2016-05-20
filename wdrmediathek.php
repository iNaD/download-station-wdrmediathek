<?php

/**
 * @author Daniel Gehn <me@theinad.com>
 * @version 0.2b
 * @copyright 2016 Daniel Gehn
 * @license http://opensource.org/licenses/MIT Licensed under MIT License
 */

require_once "provider.php";

class SynoFileHostingWDRMediathek extends TheiNaDProvider {
    protected $LogPath = '/tmp/wdr-mediathek.log';

    protected static $LINK_URI = 'http://deviceids-medp.wdr.de/ondemand/%d/%d.js';

    public function GetDownloadInfo() {
        $this->DebugLog("Getting download url $this->Url");

        $rawXML = $this->curlRequest($this->Url);

        if($rawXML === null)
        {
            return false;
        }

        if(preg_match('#deviceids-medp\.wdr\.de\/ondemand\/(\d+)\/(\d+)\.js\'#si', $rawXML, $match) === 1)
        {
            $videoId = $match[2];
            $link = sprintf(self::$LINK_URI, $match[1], $videoId);

            $this->DebugLog("Fetching $link for mobile");

            $rawJS = $this->curlRequest($link,
                array(
                    CURLOPT_USERAGENT => "Mozilla/5.0 (Linux; Android 4.1; Galaxy Nexus Build/JRN84D) AppleWebKit/535.19 (KHTML, like Gecko) Chrome/18.0.1025.166 Mobile Safari/535.19"
                )
            );

            if($rawJS === null)
            {
                return false;
            }

            if(preg_match('#storeAndPlay\((.*?)\)#si', $rawJS, $jsonMatch) === 1) {
                $jsonMobile = json_decode($jsonMatch[1]);

                // Read title from JSON
                $title = "";

                if($jsonMobile->trackerData->trackerClipTitle) {
                    $title .= $jsonMobile->trackerData->trackerClipTitle;
                }

                if($jsonMobile->trackerData->trackerClipSubcategory) {
                    if($title != "") {
                        $title .= " - ";
                    }

                    $title .= $jsonMobile->trackerData->trackerClipSubcategory;
                }

                $baseVideoLink = $jsonMobile->mediaResource->dflt->videoURL;
                $baseQuality = -1;
                if(preg_match('#' . $videoId . '_(\d+)#si', $baseVideoLink, $baseQualityMatch) === 1) {
                    $baseQuality = $baseQualityMatch[1];
                }

                $this->DebugLog("Fetching $link for non mobile to receive qualities");

                $rawJS = $this->curlRequest($link);

                if($rawJS === null)
                {
                    return false;
                }

                $bestSource = array(
                    'quality'   => -1,
                    'url'       => $baseVideoLink,
                );

                if(preg_match('#storeAndPlay\((.*?)\)#si', $rawJS, $jsonMatch) === 1) {
                    $jsonDesktop = json_decode($jsonMatch[1]);

                    if(preg_match_all('#' . $videoId . '_(\d+)#si', $jsonDesktop->mediaResource->dflt->videoURL, $qualityMatches) > 0) {

                        foreach($qualityMatches[1] as $quality) {
                            if($quality > $bestSource['quality']) {
                                $url = str_replace($videoId . '_' . $baseQuality, $videoId . '_' . $quality, $baseVideoLink);

                                $bestSource = array(
                                    'quality' => $quality,
                                    'url' => $url,
                                );
                            }
                        }
                    }
                }
            }

            if($bestSource['url'] !== '')
            {
                $url = trim($bestSource['url']);

                $DownloadInfo = array();
                $DownloadInfo[DOWNLOAD_URL] = $url;
                $DownloadInfo[DOWNLOAD_FILENAME] = $this->buildFilename($url, $title);

                return $DownloadInfo;
            }

            $this->DebugLog("Failed to determine best quality: " . $jsonMobile);

            return false;

        }

        $this->DebugLog("Couldn't identify player meta");

        return false;
    }

    protected function buildFilename($url, $title = "") {
        $pathinfo = pathinfo($url);

        if(!empty($title))
        {
            $filename = $title . '.' . $pathinfo['extension'];
        }
        else
        {
            $filename =  $pathinfo['basename'];
        }

        return $this->safeFilename($filename);
    }

}
?>
