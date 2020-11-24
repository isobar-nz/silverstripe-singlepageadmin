<?php

namespace LittleGiant\SinglePageAdmin;

use SilverStripe\Control\Controller;
use SilverStripe\Forms\Form;

/**
 * Class SinglePageCMSForm
 */
class SinglePageCMSForm extends Form
{
    /**
     * Route validation error responses through response negotiator,
     * so they return the correct markup as expected by the requesting client.
     */
    protected function getValidationErrorResponse()
    {
        $request = $this->getRequest();
        $negotiator = $this->getResponseNegotiator();

        if ($request->isAjax() && $negotiator) {
            $this->setupFormErrors();
            $result = $this->customise(
                ['EditForm' => $this]
            )->renderWith(Controller::curr()->getTemplatesWithSuffix('_Content'));

            return $negotiator->respond($request, [
                'CurrentForm' => function () use ($result) {
                    return $result;
                },
            ]);
        } else {
            return parent::getValidationErrorResponse();
        }
    }
}
