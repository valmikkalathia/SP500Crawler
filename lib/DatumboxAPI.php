<?php
/**
 * Example of API Client for Datumbox Machine Learning API.
 * 
 * @author Vasilis Vryniotis
 * @link   http://www.datumbox.com/
 * @copyright Copyright (c) 2013, Datumbox.com
 */
class DatumboxAPI {
    const version='1.0';
    
    protected $api_key;
    
  
    public function __construct($api_key) {
        $this->api_key=$api_key;
    }
    
    
    protected function CallWebService($api_method,$POSTparameters) {
        $POSTparameters['api_key']=$this->api_key;
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, 'http://api.datumbox.com/'.self::version.'/'.$api_method.'.json');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_ENCODING, 'gzip,deflate');
        curl_setopt($ch, CURLOPT_POST, true );
        curl_setopt($ch, CURLOPT_POSTFIELDS, $POSTparameters);
        $jsonreply = curl_exec ($ch);
        curl_close ($ch);
        unset($ch);
        return $jsonreply;
    }
    
    
    protected function ParseReply($jsonreply) {
        $jsonreply=json_decode($jsonreply,true);
        
        if(isset($jsonreply['output']['status']) && $jsonreply['output']['status']==1) {
            return $jsonreply['output']['result'];
        }
        
        if(isset($jsonreply['error']['ErrorCode']) && isset($jsonreply['error']['ErrorMessage'])) {
            echo $jsonreply['error']['ErrorMessage'].' (ErrorCode: '.$jsonreply['error']['ErrorCode'].')';
        }
        
        return false;
    }
    
  
    public function SentimentAnalysis($text) {
        $parameters=array(
            'text'=>$text,
        );
        
        $jsonreply=$this->CallWebService('SentimentAnalysis',$parameters);
        
        return $this->ParseReply($jsonreply);
    }
    
    
    public function TwitterSentimentAnalysis($text) {
        $parameters=array(
            'text'=>$text,
        );
        
        $jsonreply=$this->CallWebService('TwitterSentimentAnalysis',$parameters);
        
        return $this->ParseReply($jsonreply);
    }
    
 
    public function SubjectivityAnalysis($text) {
        $parameters=array(
            'text'=>$text,
        );
        
        $jsonreply=$this->CallWebService('SubjectivityAnalysis',$parameters);
        
        return $this->ParseReply($jsonreply);
    }
    
   
    public function TopicClassification($text) {
        $parameters=array(
            'text'=>$text,
        );
        
        $jsonreply=$this->CallWebService('TopicClassification',$parameters);
        
        return $this->ParseReply($jsonreply);
    }
    
    
    public function SpamDetection($text) {
        $parameters=array(
            'text'=>$text,
        );
        
        $jsonreply=$this->CallWebService('SpamDetection',$parameters);
        
        return $this->ParseReply($jsonreply);
    }
    
  
    public function AdultContentDetection($text) {
        $parameters=array(
            'text'=>$text,
        );
        
        $jsonreply=$this->CallWebService('AdultContentDetection',$parameters);
        
        return $this->ParseReply($jsonreply);
    }
    
   
    public function ReadabilityAssessment($text) {
        $parameters=array(
            'text'=>$text,
        );
        
        $jsonreply=$this->CallWebService('ReadabilityAssessment',$parameters);
        
        return $this->ParseReply($jsonreply);
    }
    
  
    public function LanguageDetection($text) {
        $parameters=array(
            'text'=>$text,
        );
        
        $jsonreply=$this->CallWebService('LanguageDetection',$parameters);
        
        return $this->ParseReply($jsonreply);
    }
    
   
    public function CommercialDetection($text) {
        $parameters=array(
            'text'=>$text,
        );
        
        $jsonreply=$this->CallWebService('CommercialDetection',$parameters);
        
        return $this->ParseReply($jsonreply);
    }
    
    
    public function EducationalDetection($text) {
        $parameters=array(
            'text'=>$text,
        );
        
        $jsonreply=$this->CallWebService('EducationalDetection',$parameters);
        
        return $this->ParseReply($jsonreply);
    }
    
    
    public function GenderDetection($text) {
        $parameters=array(
            'text'=>$text,
        );
        
        $jsonreply=$this->CallWebService('GenderDetection',$parameters);
        
        return $this->ParseReply($jsonreply);
    }
    
    
    public function TextExtraction($text) {
        $parameters=array(
            'text'=>$text,
        );
        
        $jsonreply=$this->CallWebService('TextExtraction',$parameters);
        
        return $this->ParseReply($jsonreply);
    }
    
    
    public function KeywordExtraction($text,$n) {
        $parameters=array(
            'text'=>$text,
            'n'=>$n,
        );
        
        $jsonreply=$this->CallWebService('KeywordExtraction',$parameters);
        
        return $this->ParseReply($jsonreply);
    }
    
   
    public function DocumentSimilarity($original,$copy) {
        $parameters=array(
            'original'=>$original,
            'copy'=>$copy,
        );
        
        $jsonreply=$this->CallWebService('DocumentSimilarity',$parameters);
        
        return $this->ParseReply($jsonreply);
    }
    
    
}

