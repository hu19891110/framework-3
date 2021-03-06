<?php
/**
 * Controller - base controller
 *
 * @author David Carr - dave@novaframework.com
 * @version 3.0
 */

namespace Core;

use Core\Config;
use Core\Language;
use Core\Template;
use Core\View;
use Helpers\Hooks;

use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

use App;
use Event;
use Response;


/**
 * Core controller, all other controllers extend this base controller.
 */
abstract class Controller
{
    /**
     * The requested Method by Router.
     *
     * @var string|null
     */
    private $method = null;

    /**
     * The parameters given by Router.
     *
     * @var array
     */
    private $params = array();

    /**
     * The currently used Template.
     *
     * @var string
     */
    protected $template;

    /**
     * The currently used Layout.
     *
     * @var string
     */
    protected $layout = 'default';

    /**
     * Language variable to use the languages class.
     *
     * @var string
     */
    public $language;

    /**
     * On the initial run, create an instance of the config class and the view class.
     */
    public function __construct()
    {
        // Setup the used Template to default, if it is not already defined.
        if(! isset($this->template)) {
            $this->template = Config::get('app.template');
        }

        // Initialise the Language object.
        $this->language = Language::getInstance();
    }

    /**
     * Execute the Controller Method
     * @return \Symfony\Component\HttpFoundation\Response
     *
     * @throw \Exception
     */
    public function execute($method, $params = array())
    {
        // Initialise the Controller's variables.
        $this->method = $method;
        $this->params = $params;

        // Notify the interested Listeners about the iminent Controller's execution.
        Event::fire('framework.controller.executing', array($this, $method, $params));

        // Before the Action execution stage.
        $response = $this->before();

        // When the Before Stage do not return a Symfony Response, execute the requested
        // Method with the given arguments, capturing its returned value on our response.
        if (! $response instanceof SymfonyResponse) {
            $response = call_user_func_array(array($this, $method), $params);
        }

        // After the Action execution stage.
        $this->after($response);

        // Do the final post-processing stage and return the response.
        return $this->processResponse($response);
    }

    /**
     * Create from the given result a Response instance and send it.
     *
     * @param mixed  $response
     *
     * @return \Symfony\Component\HttpFoundation\Response
     */
    protected function processResponse($response)
    {
        // If the response which is returned from the Controller's Action is null and we have
        // View instances on View's Legacy support, we will assume that we are on Legacy Mode.
        if (is_null($response)) {
            // Retrieve the Legacy View instances.
            $views = View::getLegacyViews();

            if (! empty($views)) {
                $headers = View::getLegacyHeaders();

                // Fetch every View instance and append it to the Response content.
                $content = '';

                foreach ($views as $view) {
                    $content .= $view->fetch();
                }

                // Create a Response instance from gathered information.
                $response = Response::make($content, 200, $headers);
            }
        }
        // If the response which is returned from the Controller's Action is a View instance,
        // we will assume we want to render it using the Controller's templated environment.
        else if ($response instanceof View) {
            if ($this->layout !== false) {
                $response = Template::make($this->layout, $this->template)->with('content', $response);
            }
        }

        // If the current response is not a instance of Symfony Response, we will create one.
        if (! $response instanceof SymfonyResponse) {
            $response = Response::make($response);
        }

        return $response;
    }

    /**
     * Method automatically invoked before the current Action, stopping the flight
     * when it returns false. This Method is supposed to be overriden for using it.
     */
    protected function before()
    {
        // Return true to continue the processing.
        return true;
    }

    /**
     * This method automatically invokes after the current Action and is supposed
     * to be overriden for using it.
     *
     * Note that the Action's returned value is passed to this Method as parameter.
     */
    protected function after($result)
    {
        //
    }

    /**
     * Return a translated string.
     * @return string
     */
    protected function trans($str, $code = LANGUAGE_CODE)
    {
        return $this->language->get($str, $code);
    }

    /**
     * @param  string $title
     *
     * @return \Core\Controller
     */
    protected function title($title)
    {
        View::share('title', $title);
    }

    /**
     * Return a default View instance.
     *
     * @return \Core\View
     */
    protected function getView(array $data = array())
    {
        list(, $caller) = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2);

        $baseView = ucfirst($caller['function']);

        //
        $classPath = str_replace('\\', '/', static::class);

        if (preg_match('#^App/Controllers/(.*)$#i', $classPath, $matches)) {
            $view = str_replace('/', DS, $matches[1]) .DS .$baseView;

            $module = null;
        } else if (preg_match('#^App/Modules/(.+)/Controllers/(.*)$#i', $classPath, $matches)) {
            $view = str_replace('/', DS, $matches[2]) .DS .$baseView;

            $module = $matches[1];
        } else {
            throw new BadMethodCallException('Invalid Controller namespace: ' .static::class);
        }

        return View::make($view, $data, $module);
    }

    /**
     * @return mixed
     */
    protected function getTemplate()
    {
        return $this->template;
    }

    /**
     * @return mixed
     */
    protected function getLayout()
    {
        return $this->layout;
    }

    /**
     * @return mixed
     */
    protected function getMethod()
    {
        return $this->method;
    }

    /**
     * @return array
     */
    protected function getParams()
    {
        return $this->params;
    }

    /**
     * Handle calls to missing methods on the controller.
     *
     * @param  string  $method
     * @param  array   $parameters
     * @return mixed
     *
     * @throws \BadMethodCallException
     */
    public function __call($method, $parameters)
    {
        throw new \BadMethodCallException("Method [$method] does not exist.");
    }

}
