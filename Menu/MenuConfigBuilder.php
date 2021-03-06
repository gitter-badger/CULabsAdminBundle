<?php

namespace CULabs\AdminBundle\Menu;

use Knp\Menu\FactoryInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\RouterInterface;
use Knp\Menu\MenuItem;
use Knp\Menu\ItemInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use CULabs\AdminBundle\Event\MenuEvent;
use Symfony\Component\Security\Core\SecurityContextInterface;

class MenuConfigBuilder
{
    protected $factory;
    protected $router;
    protected $event_dispatcher;
    protected $security_context;

    /**
     * @param FactoryInterface                                            $factory
     * @param \Symfony\Component\Routing\RouterInterface                  $router
     * @param \Symfony\Component\EventDispatcher\EventDispatcherInterface $event_dispatcher
     * @param \Symfony\Component\Security\Core\SecurityContextInterface   $security_context
     */
    public function __construct(FactoryInterface $factory, RouterInterface $router, EventDispatcherInterface $event_dispatcher, SecurityContextInterface $security_context)
    {
        $this->factory          = $factory;
        $this->router           = $router;
        $this->event_dispatcher = $event_dispatcher;
        $this->security_context = $security_context;
    }

    /**
     * @param  Request                 $request
     * @param  Array                   $menu_config
     * @param  Array                   $options
     * @return \Knp\Menu\ItemInterface
     */
    public function getMenu(Request $request, Array $menu_config, Array $options = array())
    {
        if (isset($menu_config['roles']) && !$this->security_context->isGranted($menu_config['roles'])) {
            return;
        }

        $menu = $this->factory->createItem('root');

        $label = isset($menu_config['label'])? $menu_config['label']: $this->buildLabel('root');
        $menu->setLabel($label);

        if (isset($menu_config['route'])) {

            $path = $this->router->generate($menu_config['route']);
            $menu->setUri($path);
        }

        $request_uri = $request->getRequestUri();
        $options['remove_get_parameters'] = isset($options['remove_get_parameters'])? $options['remove_get_parameters']: true;
        if ($options['remove_get_parameters']) {

            $request_uri = explode('?', $request_uri);
            $request_uri = $request_uri[0];
        }

        $this->event_dispatcher->dispatch(MenuEvent::CONFIGURE, new MenuEvent($this->factory, $menu));

        $this->doMenu($menu, $menu_config['items'], $request_uri);

        return $menu;
    }

    /**$
     * @param ItemInterface $menu
     * @param Array         $menu_config
     * @param string        $request_uri
     */
    protected function doMenu(ItemInterface $menu, Array $menu_config, $request_uri)
    {
        $has_items = false;
        foreach ($menu_config as $key => $item) {

            if (isset($item['roles']) && !$this->security_context->isGranted($item['roles'])) {
                continue;
            }

            $child = new MenuItem($key, $this->factory);

            $label = isset($item['label'])? $item['label']: $this->buildLabel($key);
            $child->setLabel($label);

            if (isset($item['route'])) {

                $path  = $this->router->generate($item['route']);
                $child->setUri($path);

                if ($request_uri == $path) {
                    $child->setCurrent(true);
                }
            }

            $this->event_dispatcher->dispatch(MenuEvent::CONFIGURE, new MenuEvent($this->factory, $child));

            $has_child_items = false;
            if (isset($item['items'])) {
                $has_child_items = $this->doMenu($child, $item['items'], $request_uri);
            }

            if (!$has_child_items && !isset($item['route'])) {
                continue;
            }
            $menu->addChild($child);
            $has_items = true;
        }

        return $has_items;
    }

    /**
     * @param  string $key
     * @return string
     */
    protected function buildLabel($key)
    {
        return 'menu.'.$key;
    }
}