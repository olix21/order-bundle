<?php

namespace Dywee\OrderBundle\Listener;

use Dywee\CoreBundle\DyweeCoreEvent;
use Dywee\CoreBundle\Event\AdminSidebarBuilderEvent;
use Dywee\OrderBundle\Services\OrderAdminSidebarHandler;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;


class AdminSidebarBuilderListener implements EventSubscriberInterface{
    private $orderAdminSidebarHandler;

    public function __construct(OrderAdminSidebarHandler $orderAdminSidebarHandler)
    {
        $this->orderAdminSidebarHandler = $orderAdminSidebarHandler;
    }


    public static function getSubscribedEvents()
    {
        // return the subscribed events, their methods and priorities
        return array(
            DyweeCoreEvent::BUILD_ADMIN_SIDEBAR => array('addElementToSidebar', -15)
        );
    }

    public function addElementToSidebar(AdminSidebarBuilderEvent $adminSidebarBuilderEvent)
    {
        $adminSidebarBuilderEvent->addAdminElement($this->orderAdminSidebarHandler->getSideBarMenuElement());
    }

}