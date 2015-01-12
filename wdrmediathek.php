<?php

/**
 * @author Daniel Gehn <me@theinad.com>
 * @version 0.1
 * @copyright 2015 Daniel Gehn
 * @license http://opensource.org/licenses/MIT Licensed under MIT License
 */

class SynoFileHostingWDRMediathek {
    private $Url;
    private $Username;
    private $Password;
    private $HostInfo;

    private $LogPath = '/tmp/wdr-mediathek.log';
    private $LogEnabled = false;

    public function __construct($Url, $Username = '', $Password = '', $HostInfo = '') {
        $this->Url = $Url;
        $this->Username = $Username;
        $this->Password = $Password;
        $this->HostInfo = $HostInfo;

        $this->DebugLog("URL: $Url");
    }

    //This function returns download url.
    public function GetDownloadInfo() {
        $ret = FALSE;

        $this->DebugLog("GetDownloadInfo called");

        $ret = $this->Download();

        return $ret;
    }

    public function onDownloaded()
    {
    }

    public function Verify($ClearCookie = '')
    {
        $this->DebugLog("Verifying User");

        return USER_IS_PREMIUM;
    }

    //This function gets the download url
    private function Download() {
        $this->DebugLog("Getting download url $this->Url");

        $curl = curl_init();

        curl_setopt($curl, CURLOPT_URL, $this->Url);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_USERAGENT, DOWNLOAD_STATION_USER_AGENT);
        curl_setopt($curl, CURLOPT_FOLLOWLOCATION, true);

        $rawXML = curl_exec($curl);

        if(!$rawXML)
        {
            $this->DebugLog("Failed to retrieve Website. Error Info: " . curl_error($curl));
            return false;
        }

        curl_close($curl);

        if(preg_match('#class="videoLink"\s*>\s*<a href="(.*?)"#si', $rawXML, $match) === 1)
        {
            $curl = curl_init();

            $this->DebugLog("Fetching http://www1.wdr.de$match[1]");

            curl_setopt($curl, CURLOPT_URL, 'http://www1.wdr.de' . $match[1]);
            curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($curl, CURLOPT_USERAGENT, DOWNLOAD_STATION_USER_AGENT);
            curl_setopt($curl, CURLOPT_FOLLOWLOCATION, true);

            $RawXMLData = curl_exec($curl);

            if(!$RawXMLData)
            {
                $this->DebugLog("Failed to retrieve XML. Error Info: " . curl_error($curl));
                return false;
            }

            curl_close($curl);


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
                $DownloadInfo = array();
                $DownloadInfo[DOWNLOAD_URL] = trim($bestSource['url']);

                return $DownloadInfo;
            }

            $this->DebugLog("Failed to determine best quality: " . json_encode($matches[1]));

            return FALSE;

        }

        $this->DebugLog("Couldn't identify player meta");

        return FALSE;
    }

    private function DebugLog($message)
    {
        if($this->LogEnabled === true)
        {
            file_put_contents($this->LogPath, $message . "\n", FILE_APPEND);
        }
    }
}
?>
