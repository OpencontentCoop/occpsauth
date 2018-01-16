<?php

abstract class OCCpsAuthUserHandlerAbstract implements OCCpsAuthUserHandlerInterface
{
    abstract protected function mapVarsToUserAttributesString(array $data);

    abstract protected function generateUserRemoteId(array $data);

    /**
     * @return eZUser|null
     */
    abstract protected function getExistingUser(array $data);

    public function login(array $data, eZModule $module)
    {
        $user = $this->getExistingUser($data);
        if ($user instanceof eZUser) {
            $this->loginUser($user);
            return $this->handleRedirect($module, $user);
        }

        $params = array();
        $params['creator_id'] = $this->getUserCreatorId();
        $params['remote_id'] = $this->generateUserRemoteId($data);
        $params['class_identifier'] = $this->getUserClassIdentifier();
        $params['parent_node_id'] = $this->getUserParentNodeId();
        $params['attributes'] = $this->mapVarsToUserAttributesString($data);
        $contentObject = eZContentFunctions::createAndPublishObject($params);

        if ($contentObject instanceof eZContentObject) {
            $user = eZUser::fetch($contentObject->attribute('id'));
            if ($user instanceof eZUser) {
                $cpsUser = $this->getExistingUser($data);
                if ($cpsUser instanceof eZUser && $cpsUser->id() == $user->id()) {
                	$this->loginUser($user);
            		return $this->handleRedirect($module, $user);
                }                
            }
        }

        throw new Exception("Error creating user", 1);
    }

    public function logout(eZModule $module)
    {
        if (eZHTTPTool::instance()->hasSessionVariable('CPSUserLoggedIn')){
            eZHTTPTool::instance()->removeSessionVariable('CPSUserLoggedIn');
            return $module->redirectTo('Shibboleth.sso/Logout');
        }
        return $module->redirectTo('/');        
    }

    protected function loginUser($user)
	{
	    $userID = $user->attribute('contentobject_id');

	    // if audit is enabled logins should be logged
	    eZAudit::writeAudit('user-login', array('User id' => $userID, 'User login' => $user->attribute('login')));

	    eZUser::updateLastVisit($userID, true);
	    eZUser::setCurrentlyLoggedInUser($user, $userID);

	    // Reset number of failed login attempts
	    eZUser::setFailedLoginAttempts($userID, 0);

        eZHTTPTool::instance()->setSessionVariable('CPSUserLoggedIn', true);
	}

    protected function getUserClassIdentifier()
    {
        $ini = eZINI::instance();

        return eZContentClass::classIdentifierByID($ini->variable("UserSettings", "UserClassID"));
    }

    protected function getUserCreatorId()
    {
        $ini = eZINI::instance();

        return $ini->variable("UserSettings", "UserCreatorID");
    }

    protected function getUserParentNodeId()
    {
        $ini = eZINI::instance();
        $db = eZDB::instance();
        $defaultUserPlacement = (int)$ini->variable("UserSettings", "DefaultUserPlacement");
        $sql = "SELECT count(*) as count FROM ezcontentobject_tree WHERE node_id = $defaultUserPlacement";
        $rows = $db->arrayQuery($sql);
        $count = $rows[0]['count'];
        if ($count < 1) {
            $errMsg = ezpI18n::tr('design/standard/user',
                'The node (%1) specified in [UserSettings].DefaultUserPlacement setting in site.ini does not exist!',
                null, array($defaultUserPlacement));
            throw new Exception($errMsg, 1);
        }

        return $defaultUserPlacement;
    }

    protected function handleRedirect(eZModule $module, eZUser $user)
    {        
        $ini = eZINI::instance();
        $redirectionURI = $ini->variable('SiteSettings', 'DefaultPage');
        if (is_object($user)) {
            /*
             * Choose where to redirect the user to after successful login.
             * The checks are done in the following order:
             * 1. Per-user.
             * 2. Per-group.
             *    If the user object is published under several groups, main node is chosen
             *    (it its URI non-empty; otherwise first non-empty URI is chosen from the group list -- if any).
             *
             * See doc/features/3.8/advanced_redirection_after_user_login.txt for more information.
             */

            // First, let's determine which attributes we should search redirection URI in.
            $userUriAttrName = '';
            $groupUriAttrName = '';
            if ($ini->hasVariable('UserSettings', 'LoginRedirectionUriAttribute')) {
                $uriAttrNames = $ini->variable('UserSettings', 'LoginRedirectionUriAttribute');
                if (is_array($uriAttrNames)) {
                    if (isset($uriAttrNames['user'])) {
                        $userUriAttrName = $uriAttrNames['user'];
                    }

                    if (isset($uriAttrNames['group'])) {
                        $groupUriAttrName = $uriAttrNames['group'];
                    }
                }
            }

            $userObject = $user->attribute('contentobject');

            // 1. Check if redirection URI is specified for the user
            $userUriSpecified = false;
            if ($userUriAttrName) {
                /** @var eZContentObjectAttribute[] $userDataMap */
                $userDataMap = $userObject->attribute('data_map');
                if (!isset($userDataMap[$userUriAttrName])) {
                    eZDebug::writeWarning("Cannot find redirection URI: there is no attribute '$userUriAttrName' in object '" .
                                          $userObject->attribute('name') .
                                          "' of class '" .
                                          $userObject->attribute('class_name') . "'.");
                } elseif (( $uriAttribute = $userDataMap[$userUriAttrName] )
                          && ( $uri = $uriAttribute->attribute('content') )) {
                    $redirectionURI = $uri;
                    $userUriSpecified = true;
                }
            }

            // 2.Check if redirection URI is specified for at least one of the user's groups (preferring main parent group).
            if (!$userUriSpecified && $groupUriAttrName && $user->hasAttribute('groups')) {
                $groups = $user->attribute('groups');

                if (isset($groups) && is_array($groups)) {
                    $chosenGroupURI = '';
                    foreach ($groups as $groupID) {
                        $group = eZContentObject::fetch($groupID);
                        /** @var eZContentObjectAttribute[] $groupDataMap */
                        $groupDataMap = $group->attribute('data_map');
                        $isMainParent = ( $group->attribute('main_node_id') == $userObject->attribute('main_parent_node_id') );

                        if (!isset($groupDataMap[$groupUriAttrName])) {
                            eZDebug::writeWarning("Cannot find redirection URI: there is no attribute '$groupUriAttrName' in object '" .
                                                  $group->attribute('name') .
                                                  "' of class '" .
                                                  $group->attribute('class_name') . "'.");
                            continue;
                        }
                        $uri = $groupDataMap[$groupUriAttrName]->attribute('content');
                        if ($uri) {
                            if ($isMainParent) {
                                $chosenGroupURI = $uri;
                                break;
                            } elseif (!$chosenGroupURI) {
                                $chosenGroupURI = $uri;
                            }
                        }
                    }

                    if ($chosenGroupURI) // if we've chose an URI from one of the user's groups.
                    {
                        $redirectionURI = $chosenGroupURI;
                    }
                }
            }
        }

        return $module->redirectTo( $redirectionURI );
    }
}