<?php



namespace Test\Monitoring\Forms\Command;

require_once __DIR__.'/BaseFormTest.php';
$base = __DIR__.'/../../../../../../../';
require_once $base.'modules/monitoring/application/forms/Command/ConfirmationForm.php';
require_once realpath($base.'modules/monitoring/application/forms/Command/WithChildrenCommandForm.php');
require_once realpath($base.'modules/monitoring/application/forms/Command/AcknowledgeForm.php');

use Monitoring\Form\Command\AcknowledgeForm;
use \Zend_View;
use \Zend_Test_PHPUnit_ControllerTestCase;

class AcknowledgeFormTest extends BaseFormTest
{
    const FORMCLASS = "Monitoring\Form\Command\AcknowledgeForm";
    public function testForm()
    {
        $form = $this->getRequestForm(array(), self::FORMCLASS);
        $form->buildForm();

        $this->assertCount(11, $form->getElements());
    }



    public function testValidateCorrectForm()
    {
        $form = $this->getRequestForm(array(
            'author'     => 'test1',
            'comment'    => 'test comment',
            'persistent' => '0',
            'expire'     => '0',
            'expiretime' => '',
            'sticky'     => '0',
            'notify'     => '0'
        ), self::FORMCLASS);

        $this->assertTrue(
            $form->isPostAndValid(),
            "Asserting a correct form to be validated correctly"
        );
    }

    public function testDetectMissingAcknowledgementComment()
    {
        $form = $this->getRequestForm(array(
            'author'     => 'test1',
            'comment'    => '',
            'persistent' => '0',
            'expire'     => '0',
            'expiretime' => '',
            'sticky'     => '0',
            'notify'     => '0',
        ), self::FORMCLASS);
        $this->assertFalse(
            $form->isPostAndValid(),
            "Asserting a missing comment text to cause validation errors"
        );
    }

    public function testValidateMissingExpireTime()
    {
        $form = $this->getRequestForm(array(
            'author'     => 'test1',
            'comment'    => 'test comment',
            'persistent' => '0',
            'expire'     => '1',
            'expiretime' => '',
            'sticky'     => '0',
            'notify'     => '0'
        ), self::FORMCLASS);
        $this->assertFalse(
            $form->isPostAndValid(),
            "Asserting a missing expire time to cause validation errors when expire is 1"
        );
    }

    public function testValidateIncorrectExpireTime()
    {
        $form = $this->getRequestForm(array(
            'author'     => 'test1',
            'comment'    => 'test comment',
            'persistent' => '0',
            'expire'     => '1',
            'expiretime' => 'NOT A DATE',
            'sticky'     => '0',
            'notify'     => '0'
        ), self::FORMCLASS);
        $this->assertFalse(
            $form->isPostAndValid(),
            "Assert incorrect dates to be recognized when validating expiretime"
        );
    }

    public function testValidateCorrectAcknowledgementWithExpireTime()
    {
        $form = $this->getRequestForm(array(
            'author'     => 'test1',
            'comment'    => 'test comment',
            'persistent' => '0',
            'expire'     => '1',
            'expiretime' => '2013-07-10 17:32:16',
            'sticky'     => '0',
            'notify'     => '0'
        ), self::FORMCLASS);
        $this->assertTrue(
            $form->isPostAndValid(),
            "Assert that correct expire time acknowledgement is considered valid"
        );
    }
}

