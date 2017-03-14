<?php

/**
 * Defines the Single Page Administration interface for the CMS
 *
 * @package SinglePageAdmin
 * @author Stevie Mayhew
 */
class SinglePageAdmin extends LeftAndMain implements PermissionProvider
{

    /**
     * @var string
     */
    private static $url_rule = '/$Action/$ID/$OtherID';
    /**
     * @var string
     */
    private static $menu_icon = 'silverstripe-singlepageadmin/images/singlepageadmin.png';

    /**
     * @var array
     */
    private static $allowed_actions = array(
        'EditForm'
    );

    /**
     * If multiple codes are provided, all of them are required.
     * All CMS controllers require "CMS_ACCESS_LeftAndMain" as a baseline check,
     * and fall back to "CMS_ACCESS_<class>" if no permissions are defined here.
     * See {@link canView()} for more details on permission checks.
     *
     * @config
     * @var Array Codes which are required from the current user to view this controller.
     */
    private static $required_permission_codes;

    /**
     * A cached reference to the page record
     * @var SiteTree
     */
    protected $page;


    /**
     * Initialize requirements for this view
     */
    public function init()
    {
        parent::init();
        Requirements::javascript(CMS_DIR . '/javascript/CMSMain.EditForm.js');
    }

    /**
     * Helper function for getting the single page instance, existing or created
     * @return SiteTree
     */
    protected function findOrMakePage()
    {
        if ($this->page) {
            return $this->page;
        }

        $currentStage = Versioned::current_stage();
        Versioned::reading_stage('Stage');

        $treeClass = $this->config()->get('tree_class');
        $page = $treeClass::get()->first();

        if (!$page || !$page->exists()) {
            $page = $treeClass::create();
            $page->Title = $treeClass;
            $page->write();
            $page->doPublish();
        }

        Versioned::reading_stage($currentStage);

        return $this->page = $page;
    }

    /**
     * @param null $member
     * @return bool|int
     */
    public function canView($member = null)
    {
        if(!$member && $member !== false){
            $member = Member::currentUser();
        }

        $codes = array();
        $extraCodes = $this->stat('required_permission_codes');

        if ($extraCodes !== false) { // allow explicit FALSE to disable subclass check
            if ($extraCodes) {
                $codes = array_merge($codes, (array)$extraCodes);
            } else {
                $codes[] = "CMS_ACCESS_$this->class";
            }
        }


        foreach ($codes as $code) {
            if (!Permission::checkMember($member, $code)) {
                return false;
            }
        }

        return parent::canView($member);
    }

    /**
     * @return array
     */
    public function providePermissions()
    {
        $perms = array();

        // Add any custom SinglePageAdmin subclasses.
        foreach (ClassInfo::subclassesFor('SinglePageAdmin') as $i => $class) {

            if ($class == 'SinglePageAdmin') {
                continue;
            }

            if (ClassInfo::classImplements($class, 'TestOnly')) {
                continue;
            }

            $title = _t("{$class}.MENUTITLE", LeftAndMain::menu_title_for_class($class));
            $perms["CMS_ACCESS_" . $class] = array(
                'name' => _t(
                    'CMSMain.ACCESS',
                    "Access to '{title}' section",
                    "Item in permission selection identifying the admin section. Example: Access to 'Files & Images'",
                    array('title' => $title)
                ),
                'category' => _t('Permission.CMS_ACCESS_CATEGORY', 'CMS Access')
            );
        }

        return $perms;
    }

    /**
     * @param null $id Not used.
     * @param null $fields Not used.
     * @return Form
     */
    public function getEditForm($id = null, $fields = null)
    {
        $page = $this->findOrMakePage();
        $fields = $page->getCMSFields();

        $fields->push(new HiddenField('PreviewURL', 'Preview URL', RootURLController::get_homepage_link()));
        $fields->push($navField = new LiteralField('SilverStripeNavigator', $this->getSilverStripeNavigator()));
        $navField->setAllowHTML(true);

        $currentStage = Versioned::current_stage();
        Versioned::reading_stage('Stage');

        $form = CMSForm::create(
            $this,
            'EditForm',
            $fields,
            $this->getCMSActions()
        )->setHTMLID('Form_EditForm');

        if ($page->hasMethod('getCMSValidator')) {
            $form->setValidator($page->getCMSValidator());
        }

        if ($form->Fields()->hasTabset()) {
        	$form->Fields()->findOrMakeTab('Root')->setTemplate('CMSTabSet');
        }
        $form
        	->setResponseNegotiator($this->getResponseNegotiator())
        	->addExtraClass('cms-content center cms-edit-form')
			->setHTMLID('Form_EditForm')
        	->loadDataFrom($page)
        	->setTemplate($this->getTemplatesWithSuffix('_EditForm'));

        $this->extend('updateEditForm', $form);

        Versioned::reading_stage($currentStage);

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
        return $this->findOrMakePage()->ID;
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
    	$page = $this->findOrMakePage();
        $baseLink = ($page && $page instanceof SiteTree) ? $page->Link('?stage=Stage') : Director::absoluteBaseURL();
        return $baseLink;
    }

    /**
     * @return FieldList
     */
    protected function getCMSActions()
    {
        $page = $this->findOrMakePage();
        $published = $page->isPublished();

        $actions = FieldList::create();
        $actions->push(
            FormAction::create('save', _t('SiteTree.BUTTONSAVED', 'Saved'))
                ->setAttribute('data-icon', 'accept')
                ->setAttribute('data-icon-alternate', 'addpage')
                ->setAttribute('data-text-alternate', _t('CMSMain.SAVEDRAFT', 'Save draft'))
                ->setUseButtonTag(true)
        );

        $publish = FormAction::create(
            'publish',
            $published ?
                _t('SiteTree.BUTTONPUBLISHED', 'Published') :
                _t('SiteTree.BUTTONSAVEPUBLISH', 'Save & publish')
        )
            ->setAttribute('data-icon', 'accept')
            ->setAttribute('data-icon-alternate', 'disk')
            ->setAttribute('data-text-alternate', _t('SiteTree.BUTTONSAVEPUBLISH', 'Save & publish'))
            ->setUseButtonTag(true);

        if ($page->stagesDiffer('Stage', 'Live') && $published) {
            $publish->addExtraClass('ss-ui-alternate');
            $actions->push(
                FormAction::create(
                    'rollback',
                    _t(
                        'SiteTree.BUTTONCANCELDRAFT',
                        'Cancel draft changes'
                    )
                )
                    ->setDescription(
                        _t(
                            'SiteTree.BUTTONCANCELDRAFTDESC',
                            'Delete your draft and revert to the currently published page'
                        )
                    )
                    ->setUseButtonTag(true)
            );
        }
        $actions->push($publish);

        if ($published) {
            $actions->push(
                FormAction::create(
                    'unpublish',
                    _t('SiteTree.BUTTONUNPUBLISH', 'Unpublish')
                )
                    ->addExtraClass('ss-ui-action-destructive')
                    ->setUseButtonTag(true)
            );
        }

        $this->extend('updateCMSActions', $actions);

        return $actions;
    }

    /**
     * @param $data
     * @param $form
     * @return HTMLText|SS_HTTPResponse|ViewableData_Customised
     */
    public function save($data, $form)
    {
        $currentStage = Versioned::current_stage();
        Versioned::reading_stage('Stage');
        $value = $this->doSave($data, $form);
        Versioned::reading_stage($currentStage);

        return $value;
    }

    /**
     * @param $data
     * @param $form
     * @return HTMLText|SS_HTTPResponse|ViewableData_Customised
     */
    public function publish($data, $form)
    {
        $data['__publish__'] = true;

        return $this->doSave($data, $form);
    }

    /**
     * @param $data
     * @param $form
     * @return mixed
     */
    public function doSave($data, $form)
    {
        $page = $this->findOrMakePage();
        $controller = Controller::curr();
        $publish = isset($data['__publish__']);

        if (!$page->canEdit()) {
            return $controller->httpError(403);
        }

        try {
            $form->saveInto($page);
            $page->write();
            if ($publish) {
                $page->doPublish();
            }
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

        $link = '"' . $page->Title . '"';
        $message = _t(
            $publish ? 'SinglePageAdmin.Published' : 'GridFieldDetailForm.Saved',
            ($publish ? 'Published' : 'Saved') . ' {name} {link}',
            array(
                'name' => $page->i18n_singular_name(),
                'link' => $link
            )
        );

        $form->sessionMessage($message, 'good');
        $action = $this->edit(Controller::curr()->getRequest());

        return $action;
    }

    /**
     * @return HTMLText|ViewableData_Customised
     */
    public function unPublish()
    {
        $currentStage = Versioned::current_stage();
        Versioned::reading_stage('Live');

        $page = $this->findOrMakePage();

        // This way our ID won't be unset
        $clone = clone $page;
        $clone->delete();

        Versioned::reading_stage($currentStage);

        return $this->edit(Controller::curr()->getRequest());
    }

    /**
     * @param $data
     * @param $form
     * @return HTMLText|ViewableData_Customised
     */
    public function rollback($data, $form)
    {
        $page = $this->findOrMakePage();

        if (!$page->canEdit()) {
            return Controller::curr()->httpError(403);
        }

        $page->doRollbackTo('Live');

        $this->page = DataList::create($page->class)->byID($page->ID);

        $message = _t(
            'CMSMain.ROLLEDBACKPUBv2',
            "Rolled back to published version."
        );

        $form->sessionMessage($message, 'good');

        return $this->owner->edit(Controller::curr()->getRequest());
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

}
