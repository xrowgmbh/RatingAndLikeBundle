<?php

namespace DCS\RatingBundle\EventListener;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use DCS\RatingBundle\DCSRatingEvents;
use DCS\RatingBundle\Event\RatingEvent;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Class RatingUpdateInfoEventListener
 * @package DCS\RatingBundle\EventListener
 */
class RatingUpdateInfoEventListener implements EventSubscriberInterface
{
    private $requestStack;

    public function __construct(RequestStack $requestStack)
    {
        $this->requestStack = $requestStack;
    }

    /**
     * @return array
     */
    public static function getSubscribedEvents()
    {
        return array(
            DCSRatingEvents::RATING_PRE_PERSIST => 'updatePermalink',
        );
    }

    /**
     * @param RatingEvent $event
     */
    public function updatePermalink(RatingEvent $event)
    {
        if (null === $this->requestStack->getCurrentRequest()) {
            return;
        }

        $rating = $event->getRating();
        $attributes = $this->requestStack->getCurrentRequest()->attributes;

        if (null === $rating->getPermalink()) {
            $rating->setPermalink($attributes->get('permalink'));
        }

        if (null === $rating->getSecurityRole()) {
            $rating->setSecurityRole($attributes->get('securityRole'));
        }

        if (null === $rating->getVoteType()) {
            $rating->setVoteType($attributes->get('voteType'));
        }
    }
}
