<?php
namespace Craft;

class Import_EntryService extends BaseApplicationComponent 
{

    public function getGroups()
    {
    
        // Get editable sections for user
        $editable = craft()->sections->getEditableSections();
        
        // Get sections but not singles
        $sections = array();
        foreach($editable as $section) {
            if($section->type != SectionType::Single) {
                $sections[] = $section;
            }
        }
        
        return $sections;
    
    }

    public function setModel($settings)
    {
    
        // Set up new entry model
        $entry = new EntryModel();
        $entry->sectionId = $settings['elementvars']['section'];
        $entry->typeId = $settings['elementvars']['entrytype'];
        
        return $entry;    
    
    }
    
    public function setCriteria($settings)
    {
    
        // Match with current data
        $criteria = craft()->elements->getCriteria(ElementType::Entry);
        $criteria->limit = null;
        $criteria->status = isset($settings['map']['status']) ? $settings['map']['status'] : null;
    
        // Look in same section when replacing
        $criteria->sectionId = $settings['elementvars']['section'];
        $criteria->type = $settings['elementvars']['entrytype'];
    
        return $criteria;
    
    }
    
    public function delete($elements)
    {
    
        // Delete entry
        return craft()->entries->deleteEntry($elements);
    
    }
    
    public function save(&$element, $settings)
    {
        
        // Save user
        if(craft()->entries->saveEntry($element)) {
        
            // Log entry id's when successful
            craft()->import_history->version($settings['history'], $element->id);
            
            return true;
            
        }
        
        return false;
    
    }
    
    // Prepare reserved ElementModel values
    public function prepForElementModel(&$fields, EntryModel $entry) 
    {
        
        // Set author
        $author = Import_ElementModel::HandleAuthor;
        if(isset($fields[$author])) {
            $entry->$author = intval($fields[$author]);
            unset($fields[$author]);
        } else {
            $entry->$author = ($entry->$author ? $entry->$author : (craft()->userSession->getUser() ? craft()->userSession->getUser()->id : 1));
        }
        
        // Set slug
        $slug = Import_ElementModel::HandleSlug;
        if(isset($fields[$slug])) {
            $entry->$slug = ElementHelper::createSlug($fields[$slug]);
            unset($fields[$slug]);
        }
        
        // Set postdate
        $postDate = Import_ElementModel::HandlePostDate;
        if(isset($fields[$postDate])) {
            $entry->$postDate = DateTime::createFromString($fields[$postDate], craft()->timezone);
            unset($fields[$postDate]);
        }
        
        // Set expiry date
        $expiryDate = Import_ElementModel::HandleExpiryDate;
        if(isset($fields[$expiryDate])) {
            $entry->$expiryDate = DateTime::createFromString($fields[$expiryDate], craft()->timezone);
            unset($fields[$expiryDate]);
        }
        
        // Set enabled
        $enabled = Import_ElementModel::HandleEnabled;
        if(isset($fields[$enabled])) {
            $entry->$enabled = (bool)$fields[$enabled];
            unset($fields[$enabled]);
        }
        
        // Set title
        $title = Import_ElementModel::HandleTitle;
        if(isset($fields[$title])) {
            $entry->getContent()->$title = $fields[$title];
            unset($fields[$title]);
        }
        
        // Set parent or ancestors
        $parent = Import_ElementModel::HandleParent;
        $ancestors = Import_ElementModel::HandleAncestors;
                
        if(isset($fields[$parent])) {
           
           // Get data
           $data = $fields[$parent];
            
            // Fresh up $data
           $data = str_replace("\n", "", $data);
           $data = str_replace("\r", "", $data);
           $data = trim($data);
           
           // Don't connect empty fields
           if(!empty($data)) {
         
               // Find matching element       
               $criteria = craft()->elements->getCriteria(ElementType::Entry);
               $criteria->sectionId = $entry->sectionId;

               // Exact match
               $criteria->search = '"'.$data.'"';
               
               // Return the first found element for connecting
               if($criteria->total()) {
               
                   $entry->$parent = $criteria->first()->id;
                   
               }
           
           }
           
           unset($fields[$parent]);
        
        } elseif(isset($fields[$ancestors])) {
                   
           // Get data
           $data = $fields[$ancestors];
            
            // Fresh up $data
           $data = str_replace("\n", "", $data);
           $data = str_replace("\r", "", $data);
           $data = trim($data);
           
           // Don't connect empty fields
           if(!empty($data)) {
         
               // Get section data
               $section = new SectionModel();
               $section->id = $entry->sectionId;                  
           
               // This we append before the slugified path
               $sectionUrl = str_replace('{slug}', '', $section->getUrlFormat());
                                                   
               // Find matching element by URI (dirty, not all structures have URI's)        
               $criteria = craft()->elements->getCriteria(ElementType::Entry);
               $criteria->sectionId = $entry->sectionId;
               $criteria->uri = $sectionUrl . craft()->import->slugify($data);
               $criteria->limit = 1;
                              
               // Return the first found element for connecting
               if($criteria->total()) {
               
                   $entry->$parent = $criteria->first()->id;
                   
               }
           
           }
           
           unset($fields[$ancestors]);
        
        }
        
        // Return entry
        return $entry;
                    
    }

}