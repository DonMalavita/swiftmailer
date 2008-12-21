<?php

/*
 The basic mail() wrapper from Swift Mailer.
 
 This program is free software: you can redistribute it and/or modify
 it under the terms of the GNU General Public License as published by
 the Free Software Foundation, either version 3 of the License, or
 (at your option) any later version.
 
 This program is distributed in the hope that it will be useful,
 but WITHOUT ANY WARRANTY; without even the implied warranty of
 MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 GNU General Public License for more details.

 You should have received a copy of the GNU General Public License
 along with this program.  If not, see <http://www.gnu.org/licenses/>.
 
 */

//@require 'Swift/Transport.php';
//@require 'Swift/Mime/Message.php';
//@require 'Swift/Events/EventListener.php';

/**
 * Sends Messages using the mail() function.
 * 
 * It is advised that users do not use this transport if at all possible
 * since a number of plugin features cannot be used in conjunction with this
 * transport due to the internal interface in PHP itself.
 * 
 * @package Swift
 * @subpackage Transport
 * @author Chris Corbyn
 */
class Swift_Transport_MailTransport implements Swift_Transport
{

  /** Addtional parameters to pass to mail() */
  private $_extraParams = '-f%s';
  
  /** The event dispatcher from the plugin API */
  private $_eventDispatcher;
  
  /** Loaded plugins */
  private $_plugins = array();
  
  /**
   * Create a new MailTransport with the $log.
   * @param Swift_Transport_Log $log
   */
  public function __construct(Swift_Events_EventDispatcher $eventDispatcher)
  {
    $this->_eventDispatcher = $eventDispatcher;
  }
  
  /**
   * Not used.
   */
  public function isStarted()
  {
    return false;
  }
  
  /**
   * Not used.
   */
  public function start()
  {
  }
  
  /**
   * Not used.
   */
  public function stop()
  {
  }
  
  /**
   * Set the additional parameters used on the mail() function.
   * This string is formatted for sprintf() where %s is the sender address.
   * @param string $params
   */
  public function setExtraParams($params)
  {
    $this->_extraParams = $params;
    return $this;
  }
  
  /**
   * Get the additional parameters used on the mail() function.
   * This string is formatted for sprintf() where %s is the sender address.
   * @return string
   */
  public function getExtraParams()
  {
    return $this->_extraParams;
  }
  
  /**
   * Send the given Message.
   * Recipient/sender data will be retreived from the Message API.
   * The return value is the number of recipients who were accepted for delivery.
   * @param Swift_Mime_Message $message
   * @param string[] &$failedRecipients to collect failures by-reference
   * @return int
   */
  public function send(Swift_Mime_Message $message, &$failedRecipients = null)
  {
    if ($evt = $this->_eventDispatcher->createSendEvent($this, $message))
    {
      $this->_eventDispatcher->dispatchEvent($evt, 'beforeSendPerformed');
      if ($evt->bubbleCancelled())
      {
        return 0;
      }
    }
    
    $count = (
      count($message->getTo())
      + count($message->getCc())
      + count($message->getBcc())
      );
    
    $to = $message->getHeaders()->get('To')->getFieldBody();
    $subject = $message->getHeaders()->get('Subject')->getFieldBody();
    $reversePath = $this->_getReversePath($message);
    $messageStr = $message->toString();
    
    //Separate headers from body
    if (false !== $endHeaders = strpos($messageStr, "\r\n\r\n"))
    {
      $headers = substr($messageStr, 0, $endHeaders . "\r\n"); //Keep last EOL
      $body = substr($messageStr, $endHeaders + 4);
    }
    else
    {
      $headers = $messageStr . "\r\n";
      $body = '';
    }
    
    unset($messageStr);
    
    if ("\r\n" != PHP_EOL) //Non-windows (not using SMTP)
    {
      $headers = str_replace("\r\n", PHP_EOL, $headers);
      $body = str_replace("\r\n", PHP_EOL, $body);
    }
    else //Windows, using SMTP
    {
      $headers = str_replace("\r\n.", "\r\n..", $headers);
      $body = str_replace("\r\n.", "\r\n..", $body);
    }
    
    if (mail($to, $subject, $body, $headers,
      sprintf($this->_extraParams, $reversePath)))
    {
      if ($evt)
      {
        $evt->setResult(Swift_Events_SendEvent::RESULT_SUCCESS);
        $evt->setFailedRecipients($failedRecipients);
        $this->_eventDispatcher->dispatchEvent($evt, 'sendPerformed');
      }
      
      return $count;
    }
    else
    {
      if ($evt)
      {
        $evt->setResult(Swift_Events_SendEvent::RESULT_FAILED);
        $evt->setFailedRecipients($failedRecipients);
        $this->_eventDispatcher->dispatchEvent($evt, 'sendPerformed');
      }
      
      return 0;
    }
  }
  
  /**
   * Register a plugin using a known unique key (e.g. myPlugin).
   * @param Swift_Events_EventListener $plugin
   * @param string $key
   */
  public function registerPlugin(Swift_Events_EventListener $plugin, $key)
  {
    if (isset($this->_plugins[$key]) && $this->_plugins[$key] === $plugin)
    {
      return; //already loaded
    }
    
    $this->_eventDispatcher->bindEventListener($plugin);
    $this->_plugins[$key] = $plugin;
  }
  
  // -- Private methods
  
  /**
   * Determine the best-use reverse path for this message.
   * The preferred order is: return-path, sender, from.
   * @param Swift_Mime_Message $message
   * @return string
   * @access private
   */
  private function _getReversePath(Swift_Mime_Message $message)
  {
    $return = $message->getReturnPath();
    $sender = $message->getSender();
    $from = $message->getFrom();
    $path = null;
    if (!empty($return))
    {
      $path = $return;
    }
    elseif (!empty($sender))
    {
      $keys = array_keys($sender);
      $path = array_shift($keys);
    }
    elseif (!empty($from))
    {
      $keys = array_keys($from);
      $path = array_shift($keys);
    }
    return $path;
  }
  
}
