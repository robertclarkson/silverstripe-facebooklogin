<?php


class FacebookControllerExtension extends Extension {

	private static $allowed_actions = array (
		'FacebookLoginLink',
		'FacebookCallback'
	);

	public function FacebookLoginLink() {
		if(!session_id()) {
		    session_start();
		}
		$appId = Config::inst()->get(
            'FacebookControllerExtension', 'app_id'
        );
        $secret = Config::inst()->get(
            'FacebookControllerExtension', 'api_secret'
        );
        if (!$appId || !$secret) {
            return "No facebook APP credentials";
        }

		$fb = new Facebook\Facebook([
		  'app_id' => $appId, // Replace {app-id} with your app id
		  'app_secret' => $secret,
		  'default_graph_version' => 'v2.8',
		]);

		$helper = $fb->getRedirectLoginHelper();

		$permissions = ['email', 'public_profile']; // Optional permissions
		$loginUrl = $helper->getLoginUrl(
			Controller::join_links(
				$this->owner->AbsoluteLink('FacebookCallback')), 
			$permissions);
		
		return htmlspecialchars($loginUrl);
	}

   	public function FacebookCallback() {
   		if(!session_id()) {
		    session_start();
		}
		$appId = Config::inst()->get(
            'FacebookControllerExtension', 'app_id'
        );
        $secret = Config::inst()->get(
            'FacebookControllerExtension', 'api_secret'
        );
        if (!$appId || !$secret) {
            return "No facebook APP credentials";
        }

		$fb = new Facebook\Facebook([
		  'app_id' => $appId, // Replace {app-id} with your app id
		  'app_secret' => $secret,
		  'default_graph_version' => 'v2.8',
		]);

		$helper = $fb->getRedirectLoginHelper();

		try {
			$accessToken = $helper->getAccessToken();
		} catch(Facebook\Exceptions\FacebookResponseException $e) {
			SS_Log::log('Graph returned an error: ' . $e->getMessage(), SS_Log::ERROR);
			user_error('Graph returned an error: ' . $e->getMessage());
			exit;
		} catch(Facebook\Exceptions\FacebookSDKException $e) {
			SS_Log::log('Facebook SDK returned an error: ' . $e->getMessage(), SS_Log::ERR);
			user_error('Facebook SDK returned an error: ' . $e->getMessage());
			exit;
		}

		if (! isset($accessToken)) {
			if ($helper->getError()) {
				SS_Log::log('Error: ' . $helper->getErrorCode() . $helper->getErrorReason(). $helper->getErrorDescription(), SS_Log::ERROR);
			
				header('HTTP/1.0 401 Unauthorized');
				echo "Error: " . $helper->getError() . "\n";
				echo "Error Code: " . $helper->getErrorCode() . "\n";
				echo "Error Reason: " . $helper->getErrorReason() . "\n";
				echo "Error Description: " . $helper->getErrorDescription() . "\n";
			} else {
				header('HTTP/1.0 400 Bad Request');
				echo 'Bad request';
			}
			exit;
		}
		$oAuth2Client = $fb->getOAuth2Client();
		$tokenMetadata = $oAuth2Client->debugToken($accessToken);
		$tokenMetadata->validateAppId($appId); // Replace {app-id} with your app id
		$tokenMetadata->validateExpiration();

		if (! $accessToken->isLongLived()) {
			// Exchanges a short-lived access token for a long-lived one
			try {
				$accessToken = $oAuth2Client->getLongLivedAccessToken($accessToken);
			} catch (Facebook\Exceptions\FacebookSDKException $e) {
				// echo "<p>Error getting long-lived access token: " . $helper->getMessage() . "</p>\n\n";
				SS_Log::log("<p>Error getting long-lived access token: " . $helper->getMessage(), SS_Log::ERROR);
				exit;
			}
		}

		$fb->setDefaultAccessToken($accessToken);

		try {
		  $response = $fb->get('/me?fields=id,name,first_name,last_name,link,email,picture,timezone');
		  $userNode = $response->getGraphUser();
		} catch(Facebook\Exceptions\FacebookResponseException $e) {
			SS_Log::log('Graph returned an error: ' . $e->getMessage(), SS_Log::ERROR);
			user_error('Graph returned an error: ' . $e->getMessage());
			exit;
		} catch(Facebook\Exceptions\FacebookSDKException $e) {
			SS_Log::log('Facebook SDK returned an error: ' . $e->getMessage(), SS_Log::ERR);
			user_error('Facebook SDK returned an error: ' . $e->getMessage());
			exit;
		}

		if (!$member = Member::currentUser()) {
            $member = Member::get()->filter(array(
                "FacebookUID" => $userNode->getId()
            ))->first();
            if (!$member) {
                $email = $userNode->getProperty('email');
                if ($email) {
                    $member = Member::get()->filter(array(
                        'Email' => $email
                    ))->first();
                }
            }
            if (!$member) {
                $member = Injector::inst()->create('Member');
            }
        }
        $member->syncFacebookDetails($userNode);
        $member->logIn();
		Session::set('fb_access_token', (string) $accessToken);
        return $this->owner->redirectBack();

   	}

}