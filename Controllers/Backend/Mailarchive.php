<?php

use Doctrine\ORM\AbstractQuery;
use TinectMailArchive\Models\Attachment;
use TinectMailArchive\Models\Mails;

/**
 * Class Shopware_Controllers_Backend_Mailarchive
 */
class Shopware_Controllers_Backend_Mailarchive extends Shopware_Controllers_Backend_Application implements \Shopware\Components\CSRFWhitelistAware
{

    /**
     * @var string
     */
    protected $model = Mails::class;

    public function preDispatch()
    {
        if (!in_array($this->request->getActionName(), ['download', 'downloadAttachment'])) {
            parent::preDispatch();
            $this->View()->addTemplateDir($this->container->getParameter('tinect_mail_archive.view_dir'));
        }
    }

    /**
     * Provides the last mail id for notifications
     */
    public function lastMailAction()
    {
        $this->View()->success = true;
        $this->View()->id = (int)$this->container->get('dbal_connection')->fetchColumn('SELECT id FROM s_plugin_tinectmailarchive ORDER BY id DESC LIMIT 1');
    }

    /**
     * Provides the new mails for notifications
     */
    public function getNewMailsAction()
    {
        $this->View()->success = true;
        $mails = $this->container->get('dbal_connection')->fetchAll('SELECT id, subject, receiverAddress FROM s_plugin_tinectmailarchive WHERE id > :id ORDER BY id ASC',
            ['id' => $this->Request()->getParam('id')]);
        $this->View()->mails = $mails;
    }

    /**
     * Attachment list
     */
    public function getAttachmentsAction()
    {
        $mailId = $this->Request()->getParam('mailId');

        $qb = $this->getModelManager()->createQueryBuilder();
        $result = $qb->from(Attachment::class, 'attachment')
            ->select(['attachment.id', 'attachment.fileName'])
            ->where('attachment.mail = :mailId')
            ->setParameter('mailId', $mailId)
            ->getQuery()
            ->setHydrationMode(AbstractQuery::HYDRATE_ARRAY)
            ->execute();

        $this->View()->success = true;
        $this->View()->data = $result;
        $this->View()->total = count($result);
    }

    /**
     * Download a attachment
     */
    public function downloadAttachmentAction()
    {
        $this->Front()->Plugins()->ViewRenderer()->setNoRender();

        $attachmentId = $this->Request()->getParam('id');
        $attachment = $this->getModelManager()->find(Attachment::class, $attachmentId);

        $response = $this->response;
        $response->setHeader('Content-Type', 'application/octet-stream');
        $response->setHeader('Content-Disposition',
            'attachment; filename="' . $attachment->getFileName().'"');

        $this->response->setBody(base64_decode($attachment->getContent()));
    }

    /**
     * Download a eml
     */
    public function downloadAction()
    {
        $this->Front()->Plugins()->ViewRenderer()->setNoRender();

        $emlId = $this->Request()->getParam('id');
        $eml = $this->getModelManager()->find(Mails::class, $emlId);

        $emlData = $eml->getEml();

        $response = $this->response;

        if ($emlData) {
            $response->setHeader('Content-Type', 'application/octet-stream');
            $response->setHeader('Content-Disposition',
                'attachment; filename="' . $eml->getCreated()->format('Y-m-d His') . ' ' . $eml->getSubject() . '.eml"');

            $this->response->setBody($emlData);
        } else {
            $this->response->setBody('no eml-data saved');
        }
    }

    /**
     * Clear all entries in mailbox
     */
    public function clearAction()
    {
        $this->container->get('dbal_connection')->executeQuery('SET FOREIGN_KEY_CHECKS = 0');
        $this->container->get('dbal_connection')->executeQuery('TRUNCATE TABLE s_plugin_tinectmailarchive_attachments');
        $this->container->get('dbal_connection')->executeQuery('TRUNCATE TABLE s_plugin_tinectmailarchive');
        $this->container->get('dbal_connection')->executeQuery('SET FOREIGN_KEY_CHECKS = 1');
    }

    /**
     * Returns a list with actions which should not be validated for CSRF protection
     *
     * @return string[]
     */
    public function getWhitelistedCSRFActions()
    {
        return ['downloadAttachment', 'download'];
    }
}