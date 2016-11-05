<?php

namespace DCS\RatingBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpFoundation\JsonResponse;

class RatingController extends Controller
{
    /**
     * @param $id
     * @param Request $request
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function showRateAction($id, Request $request)
    {
        $ratingManager = $this->container->get('dcs_rating.manager.rating');

        if (null === $rating = $ratingManager->findOneById($id)) {
            $rating = $ratingManager->createRating($id);
            $ratingManager->saveRating($rating);
        }

        // current user preference
        $userRating = null;
        // if unique_vote required and vote_type set to 'scale' (in other words 'stars')
        if($this->getUser() && $this->container->getParameter('dcs_rating.unique_vote')) {
            $vote = $this->container->get('dcs_rating.manager.vote')->findOneByRatingAndVoter($rating, $this->getUser());
            if ($vote) {
                $userRating = $vote->getValue();
            }
        }

        // form response data
        $rate = ($rating->getRate() != 10) ? number_format((float)$rating->getRate(), 1, '.', '') : 10;
        $response = array(
            #'rating' => $rating,
            'id'            => $rating->getId(),
            'rate'          => $rate,
            'numVotes'      => (int)$rating->getNumVotes(),
            'maxValue'      => $this->container->getParameter('dcs_rating.max_value'),
            'userRating'    => (int)$userRating,
            'style'         => $rating->get('style')
        );

        // if json result requested return JsonResponse
        $resultFormat = ($request->get('resultFormat'))
            ? $request->get('resultFormat')
            : $this->container->getParameter('dcs_rating.result_format');

        if($resultFormat == "json") {

            return new JsonResponse($response);
        }

        // select view according to voteType
        $viewName = $rating->getVoteType() == "like" ? "like" : "scale";

        return $this->render('DCSRatingBundle:Rating:'.$viewName.'.html.twig', $response);
    }

    /**
     * @param $id
     * @param Request $request
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function controlAction($id, Request $request)
    {
        $ratingManager = $this->container->get('dcs_rating.manager.rating');

        if (null === $rating = $ratingManager->findOneById($id)) {
            $rating = $ratingManager->createRating($id);
            $ratingManager->saveRating($rating);
        }

        // current user preference
        $vote = null;
        $userRating = null;
        // if unique_vote required and vote_type set to 'scale' (in other words 'stars')
        if ($this->getUser() && $this->container->getParameter('dcs_rating.unique_vote')) {
            $vote = $this->container->get('dcs_rating.manager.vote')->findOneByRatingAndVoter($rating, $this->getUser());
            if ($vote) {
                $userRating = $vote->getValue();
            }
        }

        // form response data
        $rate = ($rating->getRate() != 10) ? number_format((float)$rating->getRate(), 1, '.', '') : 10;
        $response = array(
            #'rating' => $rating,
            'id'            => $rating->getId(),
            'rate'          => $rate,
            'numVotes'      => (int)$rating->getNumVotes(),
            'params'        => $request->get('params', array()),
            'maxValue'      => $this->container->getParameter('dcs_rating.max_value'),
            'userRating'    => (int)$userRating,
            'style'         => $request->get('style')
        );

        // if json result requested return JsonResponse
        $resultFormat = ($request->get('resultFormat'))
            ? $request->get('resultFormat')
            : $this->container->getParameter('dcs_rating.result_format');

        if($resultFormat == "json") {

            return new JsonResponse($response);
        }

        // select view's prefix according to voteType
        $prefix = $rating->getVoteType() == "like" ? "like" : "scale";

        // check if the user has permission to express the vote on entity Rating
        if (!$this->container->get('security.authorization_checker')->isGranted($rating->getSecurityRole())) {
            $viewName = $prefix;
        } else {
            // check if the voting system allows multiple votes. Otherwise
            // check if the user has already expressed a preference
            if (!$this->container->getParameter('dcs_rating.unique_vote')) {
                $viewName = $prefix.'_choice';
            } else {
                $viewName = (null === $vote) ? $prefix.'_choice' : $prefix;
            }
        }

        return $this->render('DCSRatingBundle:Rating:'.$viewName.'.html.twig', $response);
    }

    /**
     * @param $id
     * @param $value
     * @param Request $request
     * @return \Symfony\Component\HttpFoundation\RedirectResponse|\Symfony\Component\HttpFoundation\Response
     * @throws \Symfony\Component\HttpKernel\Exception\BadRequestHttpException
     * @throws \Symfony\Component\HttpKernel\Exception\NotFoundHttpException
     * @throws \Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException
     */
    public function addVoteAction($id, $value, Request $request)
    {
        if (null === $rating = $this->container->get('dcs_rating.manager.rating')->findOneById($id)) {
            throw new NotFoundHttpException('Rating not found');
        }

        if (null === $rating->getSecurityRole() || !$this->container->get('security.authorization_checker')
                ->isGranted($rating->getSecurityRole())) {
            throw new AccessDeniedHttpException('You can not perform the evaluation');
        }

        $maxValue = $this->container->getParameter('dcs_rating.max_value');

        if (!is_numeric($value) || $value < 0 || $value > $maxValue) {
            throw new BadRequestHttpException(sprintf('You must specify a value between 0 and %d', $maxValue));
        }

        $user = $this->getUser();
        $voteManager = $this->container->get('dcs_rating.manager.vote');

        if ($this->container->getParameter('dcs_rating.unique_vote') &&
            null !== $voteManager->findOneByRatingAndVoter($rating, $user))
        {
            throw new AccessDeniedHttpException('You have already rated');
        }

        $vote = $voteManager->createVote($rating, $user);
        $vote->setValue($value);

        $voteManager->saveVote($vote);

        if ($request->isXmlHttpRequest()) {
            return $this->forward('DCSRatingBundle:Rating:showRate', array(
                'id' => $rating->getId()
            ));
        }

        if (null === $redirectUri = $request->headers->get('referer', $rating->getPermalink())) {
            $pathToRedirect = $this->container->getParameter('dcs_rating.base_path_to_redirect');
            if ($this->container->get('router')->getRouteCollection()->get($pathToRedirect)) {
                $redirectUri = $this->generateUrl($pathToRedirect);
            } else {
                $redirectUri = $pathToRedirect;
            }
        }

        return $this->redirect($redirectUri);
    }
}
