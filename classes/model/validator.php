<?php

namespace Foolz\Foolfuuka\Plugins\SpamGuard\Model;

use Foolz\Inet\Inet;
use Foolz\Foolframe\Model\Context;
use Foolz\Foolframe\Model\DoctrineConnection;
use Foolz\Foolframe\Model\Model;
use Symfony\Component\HttpFoundation\Request;

class Validator extends Model
{
    /**
     * @var DoctrineConnection
     */
    protected $dc;

    /**
     * @var Preferences
     */
    protected $preferences;

    public function __construct(Context $context)
    {
        parent::__construct($context);

        $this->dc = $context->getService('doctrine');
        $this->preferences = $context->getService('preferences');
    }

    public function checkComment($object)
    {
        $request = Request::createFromGlobals();
        $comment = $object->getObject();

        if ($this->preferences->get('foolfuuka.plugins.spam_guard.enable_spooky') && false === $comment->ghost_exist) {
            throw new \Foolz\Foolfuuka\Model\CommentSendingRequestCaptchaException;
        }

        if ($this->preferences->get('foolfuuka.plugins.spam_guard.enable_stopforumspam')) {
            $this->processSFS($request, $comment->comment);
        }

        if ($this->preferences->get('foolfuuka.plugins.spam_guard.enable_akismet')) {
            $this->processAkismet($request, $comment->comment);
        }
    }

    public function processAkismet($request, $comment)
    {
        $connector = new \Riv\Service\Akismet\Connector\Curl();
        $akismet = new \Riv\Service\Akismet\Akismet($connector);

        $key = $this->preferences->get('foolfuuka.plugins.spam_guard.akismet_key');
        $url = $this->preferences->get('foolfuuka.plugins.spam_guard.akismet_url');
        if ($key && $url && $akismet->keyCheck($key, $url)) {
            $data = [
                'content_type' => 'comment',
                'user_ip' => Inet::dtop($comment->poster_ip),
                'user_agent' => $request->headers->get('User-Agent'),
                'referrer' => $request->headers->get('Referer'),
                'comment_author' => $comment->name,
                'comment_author_email' => $comment->email,
                'comment_content' => $comment->comment
            ];

            if ($akismet->check($data)) {
                throw new \Foolz\Foolfuuka\Model\CommentSendingRequestCaptchaException;
            }
        }
    }

    public function processSFS($request, $comment)
    {
        $check = $this->dc->qb()
            ->select('1')
            ->from($this->dc->p('plugin_fu_spam_guard_sfs'), 'sfs')
            ->where('ip_addr_n = :ip_addr_n')
            ->setParameter(':ip_addr_n', $comment->poster_ip)
            ->execute()
            ->fetchAll();

        if (count($check) !== 0) {
            throw new \Foolz\Foolfuuka\Model\CommentSendingRequestCaptchaException;
        }
    }
}
