<?php

/**
 * @author Daniel Gehn <me@theinad.com>
 * @version 0.2a
 * @copyright 2015 Daniel Gehn
 * @license http://opensource.org/licenses/MIT Licensed under MIT License
 */

require_once "provider.php";

class SynoFileHostingWDRMediathek extends TheiNaDProvider {
    protected $LogPath = '/tmp/wdr-mediathek.log';

    public function GetDownloadInfo() {
        $this->DebugLog("Getting download url $this->Url");

        $rawXML = $this->curlRequest($this->Url);

        if($rawXML === null)
        {
            return false;
        }

        $title = "";

        if(preg_match('#<title>Video:\s*(.*?)\s*-\s*WDR Mediathek<\/title>#si', $rawXML, $match) === 1) {
            $title = $match[1];
        }

        if(preg_match('#class="videoLink"\s*>\s*<a href="(.*?)"#si', $rawXML, $match) === 1)
        {
            $this->DebugLog("Fetching http://www1.wdr.de$match[1]");

            $RawXMLData = $this->curlRequest('http://www1.wdr.de' . $match[1]);

            if($RawXMLData === null)
            {
                return false;
            }

            preg_match_all('#<a\s*rel="\w*"\s*href="(.*?)"#si', $RawXMLData, $matches);

            $bestSource = array(
                'quality'   => -1,
                'url'       => '',
            );

            foreach($matches[1] as $source)
            {
                if(strpos($source, '.mp4') !== false)
                {
                    $source = str_replace('mobile-ondemand.wdr.de', 'http-ras.wdr.de', $source);
                    if(preg_match('#_(\d+).\w+#si', $source, $qualityMatch) !== 1)
                    {
                        continue;
                    }

                    if($qualityMatch[1] > $bestSource['quality'])
                    {
                        $bestSource['quality'] = $qualityMatch[1];
                        $bestSource['url'] = $source;
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

            $this->DebugLog("Failed to determine best quality: " . json_encode($matches[1]));

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
