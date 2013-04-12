<?php

namespace li3_mailer\net\mail\transport\adapter;

use RuntimeException;

/**
 * The `Simple` adapter sends email messages with `PHP`'s built-in
 * function `mail`.
 *
 * An example configuration:
 * {{{Delivery::config(array('simple' => array(
 *     'adapter' => 'Simple', 'from' => 'my@address'
 * )));}}}
 * Apart from message parameters (like `'from'`, `'to'`, etc.) no options
 * supported.
 *
 * @see http://php.net/manual/en/function.mail.php
 * @see li3_mailer\net\mail\transport\adapter\Simple::deliver()
 * @see li3_mailer\net\mail\Delivery
 */
class Simple extends \li3_mailer\net\mail\Transport {
	/**
	 * Message property names for translating a `li3_mailer\net\mail\Message`
	 * properties to headers (these properties are addresses).
	 *
	 * @see li3_mailer\net\mail\transport\adapter\Simple::deliver()
	 * @see li3_mailer\net\mail\Message
	 * @var array
	 */
	protected $_messageAddresses = array(
		'returnPath' => 'Return-Path', 'sender', 'from',
		'replyTo' => 'Reply-To', 'cc', 'bcc'
	);

	/**
	 * Dependencies. Currently only the mail function to call,
	 * which defaults to PHP's built-in `mail()` function.
	 *
	 * @see li3_mailer\net\mail\transport\adapter\Simple::deliver()
	 * @var mixed
	 */
	protected $_dependencies = array('mail' => 'mail');

	/**
	 * Auto configuration properties.
	 *
	 * @var array
	 */
	protected $_autoConfig = array('dependencies' => 'merge');

	/**
	 * Deliver a message with `PHP`'s built-in `mail` function.
	 *
	 * @see http://php.net/manual/en/function.mail.php
	 * @see li3_mailer\net\mail\transport\adapter\Simple::$_dependencies
	 * @param object $message The message to deliver.
	 * @param array $options No options supported.
	 * @return mixed The return value of the `mail` function.
	 */
	public function deliver($message, array $options = array()) {
		$headers = $message->headers;
		foreach ($this->_messageAddresses as $property => $header) {
			if (is_int($property)) {
				$property = $header;
				$header = ucfirst($property);
			}
			$headers[$header] = $this->_address($message->$property);
		}
		$headers['Date'] = date('r', $message->date);
		$headers['MIME-Version'] = "1.0";

		$types = $message->types();
		$attachments = $message->attachments();
		$charset = $message->charset;

		// messages
		$boundary = uniqid('LI3_MAILER_SIMPLE_');
		$contentType = "multipart/alternative;boundary=\"{$boundary}\"";
		$headers['Content-Type'] = $contentType;
		$body = $attachments ? "Content-Type: {$contentType}\n\n" : '';
		foreach ($types as $type => $contentType) {
			$body .= "--{$boundary}\n";
			$contentType .= ";charset=\"{$charset}\"";
			$body .= "Content-Type: {$contentType}\n\n";
			$body .= wordwrap($message->body($type), 70) . "\n\n";
		}
		$body .= "--{$boundary}--";

		// attachments
		if ($attachments) {
			$boundary = uniqid('LI3_MAILER_SIMPLE_');
			$contentType = "multipart/mixed;boundary=\"{$boundary}\"";
			$headers['Content-Type'] = $contentType;
			$wrap = "--{$boundary}\n";
			$wrap .= $body . "\n";
			foreach ($attachments as $attachment) {
				$wrap .= "\n--{$boundary}\n";
				$attachment += array (
					'path' => null,
					'data' => false,
					'filename' => null,
					'content-type' => 'text/plain',
					'disposition' => null,
					'id' => null,
				);
				$content = $attachment['data'];
				if ($attachment['path']) {
					$content = file_get_contents($attachment['path']);
				}
				if ($content === false) {
					$error = "Can not attach path `{$attachment['path']}`.";
					throw new RuntimeException($error);
				}
				$filename = $attachment['filename'];
				if ($attachment['content-type']) {
					$contentType = $attachment['content-type'];
					if ($filename && !preg_match('/;\s+name=/', $contentType)) {
						$contentType .= "; name=\"{$filename}\"";
					}
					$wrap .= "Content-Type: {$contentType}\n";
				}
				if ($attachment['disposition']) {
					$disposition = $attachment['disposition'];
					$pattern = '/;\s+filename=/';
					if ($filename && !preg_match($pattern, $disposition)) {
						$disposition .= "; filename=\"{$filename}\"";
					}
					$wrap .= "Content-Disposition: {$disposition}\n";
				}
				if ($attachment['id']) {
					$wrap .= "Content-ID: <{$attachment['id']}>\n";
				}
				$wrap .= "Content-Transfer-Encoding: base64\n";
				$wrap .= "\n" . wordwrap(base64_encode($content), 70, "\n", true) . "\n";
			}
			$wrap .= "\n--{$boundary}--";
			$body = $wrap;
		}
		$headers = array_filter($headers);
		$headers = join ("\r\n",
			array_map (
				function ($name, $value) {
					return "{$name}: {$value}";
				},
				array_keys($headers),
				$headers
			)
		);
		$to = $this->_address($message->to);
		$mail = $this->_dependencies['mail'];
		return call_user_func($mail, $to, $message->subject, $body, $headers);
	}
}

?>
