<?php
class UNL_WDN_Assessment
{
    public $baseUri;
    
    public $db;
    
    function __construct($baseUri, $db)
    {
        $this->baseUri = $baseUri;
        $this->db      = $db;
    }
    
    /**
     * 
     * @return Spider
     */
    protected function getSpider($loggers = array(), $filters = array())
    {
        $downloader       = new Spider_Downloader();    
        $parser           = new Spider_Parser();
        $spider           = new Spider($downloader, $parser);
        
        foreach ($loggers as $logger) {
            $spider->addLogger($logger);
        }

        foreach ($filters as $filter) {
            $spider->addUriFilter($filter);
        }
        
        //Add default filters
        $spider->addUriFilter('Spider_AnchorFilter');
        $spider->addUriFilter('Spider_MailtoFilter');
        $spider->addUriFilter('UNL_WDN_Assessment_FileExtensionFilter');
        
        return $spider;
    }
    
    function checkInvalid()
    {
        $vlogger = new UNL_WDN_Assessment_ValidateInvalidLogger($this);
        $slogger = new UNL_WDN_Assessment_ValidityStatusLogger($this);
        
        $spider  = $this->getSpider(array($vlogger, $slogger));
        
        $spider->spider($this->baseUri);
    }

    /**
     * Will recheck all metrics for every page
     * (save results to DB)
     */
    function check()
    {
        $this->removeEntries();

        $uriLogger = new UNL_WDN_Assessment_URILogger($this);
        $validationLogger = new UNL_WDN_Assessment_ValidationLogger($this);
        $templateHTMLLogger = new UNL_WDN_Assessment_TemplateHTMLLogger($this);
        $templateDEPLogger = new UNL_WDN_Assessment_TemplateDEPLogger($this);
        $linkChecker = new UNL_WDN_Assessment_LinkChecker($this);

        $spider  = $this->getSpider(array($uriLogger, $validationLogger, $templateHTMLLogger, $templateDEPLogger, $linkChecker));

        $spider->spider($this->baseUri);
    }
    
    function reValidate()
    {
        
    }
    
    function removeEntries()
    {
        //Remove assessment entries
        $sth = $this->db->prepare('DELETE FROM assessment WHERE baseurl = ?');
        $sth->execute(array($this->baseUri));
        
        //remove url_has_badlinks entries
        $sth = $this->db->prepare('DELETE FROM url_has_badlinks WHERE baseurl = ?');
        $sth->execute(array($this->baseUri));
    }
    
    function getSubPages()
    {
        $sth = $this->db->prepare('SELECT * FROM assessment WHERE baseurl = ?;');
        $sth->execute(array($this->baseUri));
        return $sth->fetchAll();
    }
    
    function getBadLinksForPage($url)
    {
        $sth = $this->db->prepare('SELECT * FROM url_has_badlinks WHERE url = ?;');
        $sth->execute(array($url));
        return $sth->fetchAll();
    }
    
    function pageWasValid($uri)
    {
        if ($this->getValidityStatus($uri) == '0') {
            return true;
        }
        return false;
    }
    
    function getValidityStatus($uri)
    {
        $sth = $this->db->prepare('SELECT valid FROM assessment WHERE baseurl = ? AND url = ?;');
        $sth->execute(array($this->baseUri, $uri));
        $result = $sth->fetch();
        return $result['valid'];
    }
}