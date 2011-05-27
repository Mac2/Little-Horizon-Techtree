<?php
class Application_Model_Messages extends TechTree_Db_Model
{
    /**
     * Gets information about total message count and new message count.
     *
     * @param string $to ID of the user
     *
     * @return array
     */
    public function getMessageSummary($to)
    {
        $to = $this->_dbObject->quote($to);
        $pdoState = $this->_dbObject->query(
            "SELECT COUNT(*) msgCount, (
            SELECT COUNT(*) FROM `tt_messages` WHERE `to` = $to AND `new` = 1
            ) msgNew FROM `tt_messages` WHERE `to` = $to"
        );
        $summary = $pdoState->fetch(PDO::FETCH_ASSOC);
        $pdoState->closeCursor();
        
        return $summary;
    }

    /**
     * Gets the message body of the given message and mark it as read.
     *
     * @param string $messageId ID of the message
     * @param string $userId    ID of the currently logged in user.
     *
     * @return string
     */
    public function getMessage($messageId, $userId)
    {
        $oldMessageId = $messageId;
        $userId = $this->_dbObject->quote($userId);
        $messageId = $this->_dbObject->quote($messageId);
        $pdoState = $this->_dbObject->query("SELECT `message` FROM `tt_messages` WHERE `id` = $messageId AND `to` = $userId");
        $message = $pdoState->fetchColumn(0);
        $pdoState->closeCursor();
        if (trim($message) != '') {
            $this->markMessageAsRead($oldMessageId);
        }
        return $message;
    }

    /**
     * Deletes a message.
     *
     * @param string $messageId Id of the message to delete.
     *
     * @return void
     */
    public function deleteMessage($messageId)
    {
        $messageId = $this->_dbObject->quote($messageId);
        $this->_dbObject->query("DELETE FROM `tt_messages` WHERE `id` = $messageId");
    }

    /**
     * Set the 'message is new' status to false.
     *
     * @param string $messageId ID of the message to mark as read.
     *
     * @return void
     */
    public function markMessageAsRead($messageId)
    {
        $messageId = $this->_dbObject->quote($messageId);
        $this->_dbObject->query("UPDATE `tt_messages` SET `new` = 0 WHERE `id` = $messageId");
    }

    /**
     * Puts (sends) a new message into the database.
     *
     * @param string $from    User ID of the sender
     * @param string $to      User ID of the recipient
     * @param string $subject Message subject
     * @param string $message Message body
     * 
     * @return bool
     */
    public function addMessage($from, $to, $subject, $message)
    {
        $timestamp = time();
        $data = array(
            'TIME' => $timestamp,
            'FROM' => $from,
            'TO' => $to,
            'SUBJECT' => $subject,
            'MESSAGE' => $message,
        );
        $pdoState = $this->_dbObject->prepare(
            "INSERT INTO `tt_messages` (`id`, `from`, `to`, `subject`, `message`, `new`, `timestamp`) VALUES
            (MD5(:TIME), :FROM, :TO, :SUBJECT, :MESSAGE, 1, :TIME)"
        );
        return $pdoState->execute($data);
    }

    /**
     * Gets an overview about the messages from the given user.
     *
     * @param string $to User ID of the recipient.
     * 
     * @return array
     */
    public function getMessageOverview($to)
    {
        $to = $this->_dbObject->quote($to);
        $select = "SELECT m.`id`, m.`subject`, m.`timestamp`, m.`new`, us.`username` `from`, ur.`username` `to`
            FROM `tt_messages` m
            LEFT JOIN `tt_users` us ON us.`id` = m.`from`
            LEFT JOIN `tt_users` ur ON ur.`id` = m.`to`
            WHERE m.`to` = $to ORDER BY m.`timestamp` DESC";
        $pdoState = $this->_dbObject->query($select);
        $messages = array();
        while ($row = $pdoState->fetch(PDO::FETCH_ASSOC)) {
            $messages[$row['id']] = array(
                'from' => $row['from'],
                'to' => $row['to'],
                'subject' => $row['subject'],
                'time' => $row['timestamp'],
                'new' => (bool) $row['new'],
            );
        }
        return $messages;
    }
}
