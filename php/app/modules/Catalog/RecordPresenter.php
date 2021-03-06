<?php
namespace CatalogModule;

use App\Model;

/** @resource Catalog:Guest */
class RecordPresenter extends \BasePresenter
{
	/** @var Model\RecordModel */
	private $recordModel;
    

	public function __construct(Model\RecordModel $rm)
	{
		$this->recordModel = $rm;
	}


	public function startup()
	{
		parent::startup();
        $this->recordModel->setAppParameters($this->context->parameters);
    }
    
    private function restoreUrl($detailId, $f)
    {
        $detail = $f == 'basic' || $f == 'full' ? $f : null;
        if ($detail === null) {
            return;
        }
        if ($detailId != '') {
            $this->redirect(":Catalog:Record:$detail", $detailId);
        }
    }

    /** @resource Catalog:Guest */
	public function actionDefault($id)
	{
        if ($this->getParameter('format') == 'application/xml') {
            $this->setView('Xml');
        } else {
            if ($this->getParameter('detail') == 'full') {
                $this->setView('Full');
            } else {
                $this->setView('Basic');
            }
        }
	}

    /** @resource Catalog:Guest */
	public function renderBasic($id)
	{
        $appLang = $this->getParameter('language') !== NULL
                ? $this->getParameter('language')
                : $this->appLang;
        $langCode = array_search($appLang, $this->langCodes);
        if ($langCode) {
            $this->translator->setLocale($langCode);
        }
        $request = $_REQUEST;
        $request['service'] = 'CSW';
        $request['request'] = 'GetRecordById';
        $request['version'] = '2.0.2';
        $request['id'] = $id;
        $request['language'] = $appLang;
        $request['format'] = 'json';
        $csw = new \Micka\Csw;
        $mdr = json_decode($csw->run($csw->dirtyParams($request)));
        $request['format'] = 'text/html';
        $this->template->record = $csw->run($csw->dirtyParams($request));
        $this->template->urlParams = $this->context->getByType('Nette\Http\Request')->getQuery();
        $this->template->appLang = $appLang;
        $this->template->pageTitle = 
            $mdr !== null    
            ? $this->template->pageTitle .= ': ' . $mdr->title
            : $this->template->pageTitle .= ': ' . $this->translator->translate('messages.apperror.noRecordFound');
	}
    
    /** @resource Catalog:Guest */
	public function renderFull($id)
	{
        $appLang = $this->getParameter('language') !== NULL
                ? $this->getParameter('language')
                : $this->appLang;
        $langCode = array_search($appLang, $this->langCodes);
        if ($langCode) {
            $this->translator->setLocale($langCode);
        }
        $mdr = $this->recordModel->findMdById($id,'md','read');
        if (!$mdr) {
            throw new \Nette\Application\ApplicationException('messages.apperror.noRecordFound');
        }
        $mdf = new \App\Model\MdFull($this->context->getByType('Nette\Database\Context'), $this->user);
        $this->template->values = $mdf->getMdFullView($mdr->recno, $mdr->md_standard, $appLang);
        $this->template->rec = $mdr;
        $this->template->appLang = $appLang;
        $this->template->detail = 'full';
        $this->template->pageTitle = $this->template->pageTitle .= ': ' . $mdr->title;
	}
    
    /** @resource Catalog:Guest */
    public function renderXml($id)
    {
        $mdr = $this->recordModel->findMdById($id,'md','read');
        if (!$mdr) {
            throw new \Nette\Application\ApplicationException('messages.apperror.noRecordFound');
        } else {
            $httpResponse = $this->context->getService('httpResponse');
            $httpResponse->setContentType('application/xml');
            echo XML_HEADER.ltrim($mdr->pxml);
            $this->terminate();
        }
        
    }
    
    /** @resource Catalog:Editor */
    public function renderNew() 
    {
        $mcl = new \App\Model\CodeListModel($this->context->getByType('Nette\Database\Context'), $this->user);
        $this->template->mdStandard = $mcl->getStandardsLabel($this->appLang, TRUE);
        $this->template->groups = $this->user->getIdentity()->data['groups'];
        $this->template->edit_group = $this->context->parameters['app']['defaultEditGroup'];
        $this->template->view_group = $this->context->parameters['app']['defaultViewGroup'];;
        $this->template->mdLangs = $mcl->getLangsLabel($this->appLang);
    }
    
    /** @resource Catalog:Editor */
    public function actionCancel($id) 
    {
        $this->recordModel->deleteEditRecords();
        $this->restoreUrl($id, $this->getParam('f'));
        $this->redirect(':Catalog:Default:default');
    }
    
    /** @resource Catalog:Editor */
    public function actionSave($id) 
    {
        $post = $this->context->getByType('Nette\Http\Request')->getPost();
        if (!array_key_exists('ende', $post) || $post['ende'] != 1) {
            throw new \Nette\Application\ApplicationException('messages.apperror.postIncomplete');
        }
        $report = $this->recordModel->setFormMdValues($id, $post, $this->appLang, $this->layoutTheme);
        if (count($report) > 0) {
            $this->flashMessage($report['message'], $report['type']);
        }
        switch ($post['afterpost']) {
            case 'end':
                $this->recordModel->setEditRecord2Md();
                $this->restoreUrl($this->getParam('id'), $this->getParam('f'));
                $this->redirect(':Catalog:Default:default');
                break;
            case 'save':
                $this->recordModel->setEditRecord2Md();
                break;
            default:
        }
        $profil = $post['profil'] != $post['nextprofil']
            ? $post['nextprofil']
            : $post['profil'];
        $package = $post['package'] != $post['nextpackage']
            ? $post['nextpackage']
            : $post['package'];
        $this->redirect(':Catalog:Record:edit', [$id, 'profil'=>$profil, 'package'=>$package, 'f' => $this->getParam('f')]);
    }
    
    /** @resource Catalog:Editor */
    public function actionEdit($id) 
    {
        if($id == 'new') {
            $httpRequest = $this->context->getByType('Nette\Http\Request');
            $this->recordModel->createNewMdRecord($httpRequest);
            $mdr = $this->recordModel->getRecordMd();
            if ($mdr) {
                $profil = $mdr->md_standard == 10 
                        ? $this->context->parameters['app']['startProfil']+100 
                        : $this->context->parameters['app']['startProfil'];
                $this->redirect(':Catalog:Record:edit', [rtrim($mdr->uuid), 'profil'=>$profil, 'package'=>-1, 'f' => $this->getParam('f')]);
            } else {
                throw new \Nette\Application\ApplicationException('messages.apperror.cantSaveNew');
            }
        } else {
            if ($this->getParameter('profil') == NULL) {
                $mdr = $this->recordModel->findMdById($id,'md','edit');
                if ($mdr) {
                    $this->recordModel->copyMd2EditMd();
                } else {
                    throw new \Nette\Application\ApplicationException('messages.apperror.noRecordFound');
                }
            }
        }
    }

    /** @resource Catalog:Editor */
    public function renderEdit($id) 
    {
        $rmd = $this->recordModel->findMdById($id,'edit_md','edit');
        if ($rmd) {
            $mds = $rmd->md_standard;
            $recno = $rmd->recno;
            $md_langs = $rmd->lang;
            if ($this->getParameter('profil') != NULL) {
                $profil = $this->getParameter('profil');
            } else {
                $profil = $rmd->md_standard == 10 
                    ? $this->context->parameters['app']['startProfil']+100 
                    : $this->context->parameters['app']['startProfil'];
            }
            $package = $this->getParameter('package') ? $this->getParameter('package') : -1;
            $md_values = $this->recordModel->getRecordMdValues();
            $data = new \App\Model\MdEditForm($this->context->getByType('Nette\Database\Context'), $this->user);
            $data->setAppParameters($this->context->parameters);
            $data->appLang = $this->appLang;
            
            $mdDataType = [];
            if ($this->context->parameters['app']['mdDataType'] != '') {
                eval('$tmp=['.$this->context->parameters['app']['mdDataType'].'];');
                foreach ($tmp as $key => $value) {
                    $mdDataType[$key] = $this->translator->translate('messages.frontend.'.$value);
                }
            }
            $this->template->record = [
                'recno'=>$recno,
                'uuid'=>$id,
                'mds'=>$mds,
                'langs'=>$md_langs,
                'hierarchy'=>'application',
                'title'=>$this->recordModel->getMdTitle($md_values,$this->appLang)
                ];
            $mcl = new \App\Model\CodeListModel($this->context->getByType('Nette\Database\Context'), $this->user);
            $mcl->setLiteProfiles($this->appLang, $mds, $this->layoutTheme);
            $this->template->dataBox = $data->getEditLiteForm($rmd, $profil, $mcl->getEditLiteProfile($profil));
            $this->template->formData =
                    $this->template->dataBox == ''
                    ? $data->getEditForm($mds, $recno, $md_langs, $profil, $package, $md_values)
                    : [];
            $this->template->valuesUri = $data->getValuesUri($recno);
            $this->template->selectPackage = $package;
            $this->template->selectProfil = $profil;
            $this->template->MdDataTypes = $mdDataType;
            $this->template->dataType = $rmd->data_type;
            $this->template->view_group = $rmd->view_group;
            $this->template->edit_group = $rmd->edit_group;
            $this->template->groups = $this->user->getIdentity()->data['groups'];
            $this->template->mdControl = ($mds == 0 || $mds == 10) 
                    ? mdControl($rmd->pxml, $this->appLang)
                    : [];
            $this->template->profils = $mcl->getMdProfils($this->appLang,$mds);
            $this->template->packages = $mcl->getMdPackages($this->appLang, $mds, $profil);
            $this->template->allLanguages = $mcl->getLangsLabel($this->appLang);
            $this->template->selectLanguages = explode('|',$rmd->lang);
            $this->template->detail = $this->getParam('f');
        } else {
            throw new \Nette\Application\ApplicationException('messages.apperror.noRecordFound');
        }
    }
        
    /** @resource Catalog:Editor */
    public function renderValid($id)
    {
        $md = $this->recordModel->findMdById($id,'md','read');
        if (!$md) {
             throw new \Nette\Application\BadRequestException;
        }
        require_once $this->context->parameters['appDir'] . '/model/validator/resources/Validator.php';
        $validator = new \Validator('gmd', $this->appLang == 'cze' ? 'cze' : 'eng');
        $validator->run($md->pxml);
        $this->template->record = $validator->asHTML();
    }
    
    /** @resource Catalog:Editor */
    public function renderClone($id)
    {
        $mdr = $this->recordModel->findMdById($id,'md','view');
        if ($mdr) {
            $uuid = $this->recordModel->copyMd2EditMd('clone');
            $profil = $mdr->md_standard == 10 
                    ? $this->context->parameters['app']['startProfil']+100 
                    : $this->context->parameters['app']['startProfil'];
            $this->redirect(':Catalog:Record:edit', [$uuid, 'profil'=>$profil, 'package'=>-1]);
        } else {
            throw new \Nette\Application\ApplicationException('messages.apperror.noRecordFound');
        }
    }
    
    /** @resource Catalog:Editor */
    public function renderDelete($id)
    {
        $this->recordModel->deleteMdById($id);
        $this->redirect(':Catalog:Default:default');
    }
    
}
