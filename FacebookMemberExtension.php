<?php
/**
 * Facebook member class to wrap the member functionality of the Facebook
 * members into the member object.
 *
 * An extension to the built in {@link Member} class this adds the fields which
 * may be required as part of the member
 *
 * @package facebookconnect
 */
class FacebookMemberExtension extends DataExtension
{
    
    /**
     * @var array
     */
    private static $db = array(
        'FacebookUID'        => 'Varchar(255)',
        'FacebookLink'        => 'Varchar(255)',
        'FacebookTimezone'    => 'Varchar(255)',
        'FacebookAccessToken' => 'Varchar',
        'FacebookPicture' => 'Varchar(255)'
    );
    
    public function updateCMSFields(FieldList $fields)
    {
        if($fields->getField('FacebookUID')){
            $fields->makeFieldReadonly('FacebookUID');
            $fields->makeFieldReadonly('FacebookLink');
            $fields->makeFieldReadonly('FacebookTimezone');
            $fields->makeFieldReadonly('FacebookPicture');
        }
    }
    
    /**
     * Takes one of 'square' (50x50), 'small' (50xXX) or 'large' (200xXX)
     *
     * @return string $type
     */
    public function getAvatar($type = "square")
    {
        $controller = Controller::curr();
        if ($controller && ($session = $controller->getFacebookSession())) {
            try {
                $request = (new FacebookRequest($session, 'GET', "/me/picture?type=$type&redirect=false"))->execute();
                $picture = $request->getGraphObject()->asArray();
                // asArray returns a stdObject?
                return (is_array($picture)) ? $picture['url'] : $picture->url;
            } catch (FacebookRequestException $e) {
                SS_Log::log($e, SS_Log::ERR);
            }
        }
    }
    /**
     * Sync the new data from a users Facebook profile to the member database.
     *
     * @param Facebook\GraphUser $result
     * @param bool $sync Flag to whether we override fields like first name
     */
    public function updateFacebookFields($result, $override = true)
    {
        $this->owner->FacebookLink    = $result->getLink();
        $this->owner->FacebookUID    = $result->getProperty('id');
        $this->owner->FacebookTimezone = $result->getProperty('timezone');
        $pictureNode = $result->getProperty('picture');
        if($pictureNode){
            $this->owner->FacebookPicture = $pictureNode->getUrl();
        }
        if ($override) {
            $email = $result->getProperty('email');
            if ($email && !$this->owner->Email || !Email::validEmailAddress($this->owner->Email)) {
                $this->owner->Email = $email;
            }
            $this->owner->FirstName    = $result->getProperty('first_name');
            $this->owner->Surname    = $result->getProperty('last_name');
        }
        $this->owner->extend('onUpdateFacebookFields', $result);
    }
    /**
     * @param Member
     *
     * @return Member
     */
    public function syncFacebookDetails($info)
    {
        $sync = Config::inst()->get('FacebookControllerExtension', 'sync_member_details');
        $create = Config::inst()->get('FacebookControllerExtension', 'create_member');
        $this->owner->updateFacebookFields($info, $sync);
        // sync details	to the database
        if (($this->owner->ID && $sync) || $create) {
            if ($this->owner->isChanged()) {
                $this->owner->write();
            }
        }
        // ensure members are in the correct groups
        if ($groups = Config::inst()->get('FacebookControllerExtension', 'member_groups')) {
            foreach ($groups as $group) {
                $this->owner->addToGroupByCode($group);
            }
        }
    }
}