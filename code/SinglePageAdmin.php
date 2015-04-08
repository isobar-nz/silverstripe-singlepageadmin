<?php

/**
 * Defines the Single Page Administration interface for the CMS
 *
 * @package SinglePageAdmin
 * @author Stevie Mayhew
 */
class SinglePageAdmin extends LeftAndMain implements PermissionProvider
{

    private static $url_rule = '/$Action/$ID/$OtherID';
    private static $menu_icon = 'silverstripe-singlepageadmin/images/singlepageadmin.png';

    private static $allowed_actions = array(
        'EditForm'
    );

    public function canView($member = null)
    {
        return Permission::check("CMS_ACCESS_SinglePageAdmin");
    }

    public function providePermissions()
    {

        return array(
            "CMS_ACCESS_SinglePageAdmin" => array(
                'name' => "Access to Single Page Administration",
                'category' => 'CMS Access',
                'help' => 'Allow use of Single Page Administration'
            )
        );
    }

    /**
     * @param null $id Not used.
     * @param null $fields Not used.
     * @return Form
     */
    public function getEditForm($id = null, $fields = null)
    {
        $treeClass = $this->config()->get('tree_class');

        $page = $treeClass::get()->first();
        if (!$page || !$page->exists()) {
            $currentStage = Versioned::current_stage();
            Versioned::reading_stage('Stage');
            $page = $treeClass::create();
            $page->Title = $treeClass;
            $page->write();
            $page->doPublish();
            Versioned::reading_stage($currentStage);
        }
        $fields = $page->getCMSFields();

        $fields->push(new HiddenField('PreviewURL', 'Preview URL', RootURLController::get_homepage_link()));
        $fields->push($navField = new LiteralField('SilverStripeNavigator', $this->getSilverStripeNavigator()));
        $navField->setAllowHTML(true);

        $actions = new FieldList();
        $actions->push(
            FormAction::create('doSave', 'Save')
                ->setUseButtonTag(true)
                ->addExtraClass('ss-ui-action-constructive')
                ->setAttribute('data-icon', 'accept')
        );
        $form = CMSForm::create(
            $this, 'EditForm', $fields, $actions
        )->setHTMLID('Form_EditForm');
        $form->setResponseNegotiator($this->getResponseNegotiator());
        $form->addExtraClass('cms-content center cms-edit-form');
        if ($form->Fields()->hasTabset()) $form->Fields()->findOrMakeTab('Root')->setTemplate('CMSTabSet');
        $form->setHTMLID('Form_EditForm');
        $form->loadDataFrom($page);
        $form->setTemplate($this->getTemplatesWithSuffix('_EditForm'));

        // Use <button> to allow full jQuery UI styling
        $actions = $actions->dataFields();
        if ($actions) foreach ($actions as $action) $action->setUseButtonTag(true);

        $this->extend('updateEditForm', $form);

        return $form;

    }

    /**
     * Return the edit form
     *
     * @param null $request
     * @return Form
     */
    public function EditForm($request = null)
    {
        return $this->getEditForm();
    }

    /**
     * This function is necessary for for some module functionality that relies on the controller having the current
     * page ID implemented
     *
     * @return mixed
     */
    public function currentPageID()
    {
        $treeClass = $this->config()->get('tree_class');
        return $treeClass::get()->first()->ID;
    }

    /**
     * Used for preview controls, mainly links which switch between different states of the page.
     *
     * @return ArrayData
     */
    public function getSilverStripeNavigator()
    {
        return $this->renderWith('SinglePageAdmin_SilverStripeNavigator');
    }

    /**
     * @return mixed
     */
    public function getResponseNegotiator()
    {
        $neg = parent::getResponseNegotiator();
        $controller = $this;
        $neg->setCallback('CurrentForm', function () use (&$controller) {
            return $controller->renderWith($controller->getTemplatesWithSuffix('_Content'));
        });
        return $neg;
    }

    /**
     * @return mixed
     */
    public function LinkPreview()
    {

        $treeClass = $this->config()->get('tree_class');
        $record = $treeClass::get()->first();
        $baseLink = ($record && $record instanceof Page) ? $record->Link('?stage=Stage') : Director::absoluteBaseURL();
        return $baseLink;
    }

    /**
     * @return FieldList
     */
    public function getCMSActions()
    {
        $actions = new FieldList();
        $actions->push(
            FormAction::create('save_siteconfig', _t('CMSMain.SAVE', 'Save'))
                ->addExtraClass('ss-ui-action-constructive')->setAttribute('data-icon', 'accept')
        );
        $this->extend('updateCMSActions', $actions);

        return $actions;
    }

    /**
     * @param $data
     * @param $form
     * @return mixed
     */
    public function doSave($data, $form)
    {
        $treeClass = $this->config()->get('tree_class');
        $page = $treeClass::get()->first();

        $currentStage = Versioned::current_stage();
        Versioned::reading_stage('Stage');

        $controller = Controller::curr();
        if (!$page->canEdit()) {
            return $controller->httpError(403);
        }

        try {
            $form->saveInto($page);
            $page->write();
        } catch (ValidationException $e) {
            $form->sessionMessage($e->getResult()->message(), 'bad');
            $responseNegotiator = new PjaxResponseNegotiator(array(
                'CurrentForm' => function () use (&$form) {
                        return $form->forTemplate();
                    },
                'default' => function () use (&$controller) {
                        return $controller->redirectBack();
                    }
            ));
            if ($controller->getRequest()->isAjax()) {
                $controller->getRequest()->addHeader('X-Pjax', 'CurrentForm');
            }
            return $responseNegotiator->respond($controller->getRequest());
        }

        Versioned::reading_stage($currentStage);
        if ($page->isPublished()) {
            $this->publish($data, $form);
        }

        $link = '"' . $page->Title . '"';
        $message = _t(
            'GridFieldDetailForm.Saved',
            'Saved {name} {link}',
            array(
                'name' => $page->Title,
                'link' => $link
            )
        );

        $form->sessionMessage($message, 'good');
        $action = $this->edit(Controller::curr()->getRequest());

        return $action;
    }

    /**
     * @param $request
     * @return mixed
     */
    public function edit($request)
    {
        $controller = Controller::curr();
        $form = $this->EditForm($request);

        $return = $this->customise(array(
            'Backlink' => $controller->hasMethod('Backlink') ? $controller->Backlink() : $controller->Link(),
            'EditForm' => $form,
        ))->renderWith('SinglePageAdmin_Content');

        if ($request->isAjax()) {
            return $return;
        } else {
            return $controller->customise(array(
                'Content' => $return,
            ));
        }
    }

    /**
     * @param $data
     * @param $form
     */
    private function publish($data, $form)
    {
        $currentStage = Versioned::current_stage();
        Versioned::reading_stage('Stage');

        $treeClass = $this->config()->get('tree_class');
        $page = $treeClass::get()->first();

        if ($page) {
            $page->doPublish();
            $form->sessionMessage($page->getTitle() . ' has been saved.', 'good');
        } else {
            $form->sessionMessage('Something failed, please refresh your browser.', 'bad');
        }

        Versioned::reading_stage($currentStage);
    }

}