<?php

namespace Dywee\OrderBundle\Controller;

use Dywee\OrderBundle\Entity\BaseOrder;
use Dywee\OrderBundle\Form\BaseOrderType;
use Dywee\OrderBundle\Form\BaseOrderRentType;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class OrderController extends Controller
{
    public function tableAction(Request $request)
    {
        $or = $this->getDoctrine()->getManager()->getRepository('DyweeOrderBundle:BaseOrder');

        $state = $request->query->get('state') ?? BaseOrder::STATE_IN_PROGRESS;

        $query = $or->FindAllForPagination($state);

        $paginator  = $this->get('knp_paginator');
        $pagination = $paginator->paginate(
            $query,
            $request->query->get('page', $page)/*page number*/,
            30/*limit per page*/
        );

        return $this->render('DyweeOrderBundle:Order:table.html.twig', array('pagination' => $pagination));
    }

    public function viewAction(BaseOrder $order)
    {
        return $this->render('DyweeOrderBundle:Order:view.html.twig', array('order' => $order));
    }

    public function addAction($type = null, Request $request)
    {
        $em = $this->getDoctrine()->getManager();


        $order = new BaseOrder();
        $order->setIsPriceTTC(/*$this->getParameter('order_bundle_is_price_ttc')*/ true);

        if($type == 'rent')
            $order->setType(BaseOrder::TYPE_ONLY_RENT);

        $form = $this->get('form.factory')->create(BaseOrderType::class, $order);

        if($form->handleRequest($request)->isValid())
        {
            $em->persist($order);
            $em->flush();

            return $this->redirect($this->generateUrl('order_view', array('id' => $order->getId())));
        }
        return $this->render('DyweeOrderBundle:Order:add.html.twig', array('form' => $form->createView()));
    }

    public function updateAction(BaseOrder $order, Request $request)
    {
        //Si c'est une commande de vente
        $form = $this->get('form.factory')->create(BaseOrderType::class, $order);

        if($form->handleRequest($request)->isValid())
        {
            $em = $this->getDoctrine()->getManager();
            $order->forcePriceCalculation();
            $em->persist($order);
            $em->flush();

            return $this->redirect($this->generateUrl('order_view', array('id' => $order->getId())));
        }

        return $this->render('DyweeOrderBundle:Order:edit.html.twig', array('order' => $order, 'form' => $form->createView()));
    }

    public function deleteAction(BaseOrder $order)
    {
        $em = $this->getDoctrine()->getManager();

        $em->remove($order);
        $em->flush();
        $this->get('session')->getFlashBag()->add('success', 'Commande Bien effacée');

        return $this->redirect($this->generateUrl('order_admin_table'));
    }

    /*public function exportToCSVAction($type, $data = false)
    {
        $em = $this->getDoctrine()->getManager();
        $or = $em->getRepository('DyweeOrderBundle:BaseOrder');
        $os = $or->findByMonth('current', $type);

        //return $this->render('DyweeOrderBundle:Order:CSVExport.html.twig', array('orderList' => $orders));

        $response = $this->render('DyweeOrderBundle:Order:CSVExport.html.twig', array('orderList' => $os));
        $response->setStatusCode(200);
        $response->headers->set('Content-Type', 'text/csv; charset=UTF-8');
        $response->headers->set('Content-Description', 'Submissions Export');
        $response->headers->set('Content-Disposition', 'attachment; filename="export.csv"');
        $response->headers->set('Content-Transfer-Encoding', 'binary');
        $response->headers->set('Pragma', 'no-cache');
        $response->headers->set('Expires', '0');

        return $response;
    }*/

    /*public function currentTableAction($state)
    {
        $em = $this->getDoctrine()->getManager();
        $or = $em->getRepository('DyweeOrderBundle:BaseOrder');

        $os = $or->findByMonth('current', $state);

        return $this->render('DyweeOrderBundle:Order:table.html.twig', array('orderList' => $os));
    }*/

    public function paypalConfirmationAction($type, $reference, Request $request)
    {
        $em = $this->getDoctrine()->getManager();
        $or = $em->getRepository('DyweeOrderBundle:BaseOrder');

        $order = $or->findOneBy(
            array(
                'paymentInfos' => $request->query->get('paymentId'),
                'reference'     => $reference
            )
        );

        if($order)
        {
            $this->get('session')->set('validatedOrderReference', $order->getReference());
            $data = array('order' => $order);

            $apiContext = new ApiContext(
                new OAuthTokenCredential(
                    $this->container->getParameter('paypal.clientID'),
                    $this->container->getParameter('paypal.clientSecret')
                )
            );

            // Comment this line out and uncomment the PP_CONFIG_PATH
            // 'define' block if you want to use static file
            // based configuration

            $apiContext->setConfig(
                array(
                    'mode' => $this->container->getParameter('paypal.mode'),
                    'log.LogEnabled' => true,
                    'log.FileName' => '../PayPal.log',
                    'log.LogLevel' => $this->container->getParameter('paypal.logLevel'), // PLEASE USE `FINE` LEVEL FOR LOGGING IN LIVE ENVIRONMENTS, DEBUG for testing
                    'validation.level' => 'log',
                    'cache.enabled' => true,
                    // 'http.CURLOPT_CONNECTTIMEOUT' => 30
                    // 'http.headers.PayPal-Partner-Attribution-Id' => '123123123'
                )
            );

            $paymentId = $request->query->get('paymentId');
            $payment = Payment::get($paymentId, $apiContext);

            // PaymentExecution object includes information necessary
            // to execute a PayPal account payment.
            // The payer_id is added to the request query parameters
            // when the user is redirected from paypal back to your site
            $execution = new PaymentExecution();
            $execution->setPayerId($request->query->get('PayerID'));


            if($order->getDeliveryMethod() === '24R')
            {
                $client = new \nusoap_client('http://www.mondialrelay.fr/WebService/Web_Services.asmx?WSDL', true);

                $explode = explode('-', $order->getDeliveryInfo());

                $params = array(
                    'Enseigne' => "BEBLCBLC",
                    'Num' => $explode[1],
                    'Pays' => $explode[0]
                );

                $security = '';
                foreach($params as $param)
                    $security .= $param;
                $security .= 'xgG1mpth';

                $params['Security'] = strtoupper(md5($security));

                $result = $client->call('WSI2_AdressePointRelais', $params, 'http://www.mondialrelay.fr/webservice/', 'http://www.mondialrelay.fr/webservice/WSI2_AdressePointRelais');

                if($result['WSI2_AdressePointRelaisResult']['STAT'] == 0)
                {
                    $data['relais'] = array(
                        'address1'  => $result['WSI2_AdressePointRelaisResult']['LgAdr1'],
                        'address2'  => $result['WSI2_AdressePointRelaisResult']['LgAdr3'],
                        'zip'       => $result['WSI2_AdressePointRelaisResult']['CP'],
                        'cityString' => $result['WSI2_AdressePointRelaisResult']['Ville']
                    );
                }
            }

            $order->setValidationDate(new \DateTime());

            $message = \Swift_Message::newInstance()
                ->setSubject('Confirmation de votre commande')
                ->setFrom('no-reply@dywee.com')
                ->setTo($order->getBillingAddress()->getEmail())
                ->setBody($this->renderView('DyweeOrderBundle:Email:mail-step1.html.twig', $data))
            ;
            $message->setContentType("text/html");

            $order->setState(1);

            $notification = new Notification();
            $notification->setContent('Une nouvelle commande a été passée');
            $notification->setBundle('order');
            $notification->setType('order.new');
            $notification->setRoutingPath('order_view');
            $notification->setRoutingArguments(json_encode(array('id' => $order->getId())));

            $em->persist($order);
            $em->persist($notification);
            $em->flush();

            $this->get('mailer')->send($message);

            return $this->redirect($this->generateUrl('order_validated'));
        }
        else throw $this->createNotFoundException('Commande introuvable');

    }

    public function validatedAction()
    {
        $em = $this->getDoctrine()->getManager();

        $order = new BaseOrder();

        $em->persist($order);
        $em->flush();

        $this->get('session')->set('order', $order);

        $reference = $this->get('session')->get('validatedOrderReference');

        if($reference)
            return $this->render('DyweeOrderBundle:Order:validated.html.twig',
                array(
                    'order' => $order,
                    'validatedOrderReference' => $reference
                )
            );

        else throw $this->createNotFoundException('Commande introuvable');
    }
}