<?php
/**
 * SessionGuard - Implements a Session post-processing.
 *
 * @author Virgil-Adrian Teaca - virgil@giulianaeassociati.com
 * @version 3.0
 */

namespace Session;

use Foundation\Application;
use Session\SessionInterface;


class SessionGuard
{
    /**
     * The Application instance being handled.
     *
     * @var \Foundation\Application
     */
    protected $app;

    /**
     * Class constuctor
     *
     * @return void
     */
    protected function __construct(Application $app)
    {
        $this->app = $app;
    }

    /**
     * Finalize the Session Store
     *
     * @return void
     */
    public static function handle(Application $app)
    {
        $processor = new static($app);

        $processor->process();
    }

    protected function process()
    {
        $session = $this->app['session.store'];

        // Save the Session Store's data.
        $session->save();

        // Get the Session Store configuration.
        $config = $this->app['config']['session'];

        // Queue a proper Session Cookie.
        $this->queueSessionCookie($session, $config);

        // Collect the garbage for the Session Store instance.
        $this->collectSessionGarbage($session, $config);
    }

    /**
     * Remove the garbage from the session if necessary.
     *
     * @param  \Illuminate\Session\SessionInterface  $session
     * @param  array $config
     * @return void
     */
    protected function queueSessionCookie(SessionInterface $session, array $config)
    {
        $cookieJar = $this->app['cookie'];

        // Store the Session ID in a Cookie.
        $cookie = $cookieJar->make(
            $config['cookie'],
            $session->getId(),
            $config['lifetime'],
            $config['path'],
            $config['domain'],
            $config['secure'],
            false
        );

        $cookieJar->queue($cookie);
    }

    /**
     * Remove the garbage from the session if necessary.
     *
     * @param  \Illuminate\Session\SessionInterface  $session
     * @param  array $config
     * @return void
     */
    protected function collectSessionGarbage(SessionInterface $session, array $config)
    {
        $lifeTime = $config['lifetime'] * 60; // The option is in minutes.

        // Here we will see if this request hits the garbage collection lottery by hitting
        // the odds needed to perform garbage collection on any given request. If we do
        // hit it, we'll call this handler to let it delete all the expired sessions.
        if ($this->configHitsLottery($config))  {
            $session->getHandler()->gc($lifeTime);
        }
    }

    /**
     * Determine if the configuration odds hit the lottery.
     *
     * @param  array  $config
     * @return bool
     */
    protected function configHitsLottery(array $config)
    {
        return (mt_rand(1, $config['lottery'][1]) <= $config['lottery'][0]);
    }

}
