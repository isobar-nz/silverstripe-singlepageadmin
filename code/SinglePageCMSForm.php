<?php

use SilverStripe\Forms\Form;

/**
 * Class SinglePageCMSForm
 */
//class SinglePageCMSForm extends CMSForm
class SinglePageCMSForm extends Form
{

//    protected $responseNegotiator;
//
//    public function getResponseNegotiator() {
//        /* @var $controller \SilverStripe\Admin\LeftAndMain */
//        $controller = $this->getController();
//        $controller->getResponseNegotiator();
//
//        return $this->getController()->resp;
//    }

    /**
     * Route validation error responses through response negotiator,
     * so they return the correct markup as expected by the requesting client.
     */
    protected function getValidationErrorResponse() {
        $request = $this->getRequest();
        $negotiator = $this->getResponseNegotiator();

        if($request->isAjax() && $negotiator) {
            $this->setupFormErrors();
            $result = $this->customise(
                array('EditForm' => $this)
            )->renderWith(Controller::curr()->getTemplatesWithSuffix('_Content'));

            return $negotiator->respond($request, array(
                'CurrentForm' => function() use($result) {
                    return $result;
                }
            ));
        } else {
            return parent::getValidationErrorResponse();
        }
    }
}
