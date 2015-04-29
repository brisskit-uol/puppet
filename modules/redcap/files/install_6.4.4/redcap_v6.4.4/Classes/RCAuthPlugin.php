<?php
/**
 * Allows plugins to implement their own authentication method that will be
 * processed by REDCap's `Authentication` class. Basic workflow:
 * 
 * 1. Authentication::authenticate() checks whether the authentication
 *    method begins with "urn:rcauthplugin:"
 * 2. Authentication class $cls instantiated via RCAuthPlugin::createAuth($auth_meth)
 * 3. if ($cls->isAuthenticated()) {
 *        // set logged-in user via $cls->getUsername()
 *    }
 *    else {
 *        // route into custom authentication workflow, and eventually redirect
 *        // back to REDCap where isAuthenticated() will be checked again
 *        $cls->authenticate();
 *    }
 */
abstract class RCAuthPlugin {
    
    /**
     * Creates a new RCAuthPlugin instance from the given authentication method.
     * @param string $auth_meth a URN of the form:
     * "urn:rcauthplugin:my/plugin/dir/MyAuthClass". Note that "my/plugin/dir"
     * is assumed to live in REDCap's `plugins` directory.
     */
    public static function createAuth($auth_meth) {
        if (preg_match('/^urn:rcauthplugin:(.*\/)(\w+)$/i', $auth_meth, $matches) !== 1)
            throw new Exception("Bad auth URN: $auth_meth");
        $cls = $matches[2];
        $file = dirname(APP_PATH_DOCROOT) . "/plugins/{$matches[1]}{$cls}.php";
        if (!file_exists($file))
            throw new Exception("Bad plugin auth file: $file");
        require_once($file);
        return new $cls();
    }
    
    /**
     * Checks whether the client is authenticated.
     * @return boolean true if the user is authenticated, false if not.
     */
    public abstract function isAuthenticated();
    
    /**
     * Performs the actual authentication, followed by a redirect back to
     * REDCap where $this->isAuthenticated() will be checked.
     */
    public abstract function authenticate();
    
    /**
     * @return string the username of the authenticated user.
     */
    public abstract function getUsername();
}