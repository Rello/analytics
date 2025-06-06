<?php
namespace OCP\Notification;

interface INotification {
    public function setApp(string $app);
    public function setObject(string $object, int $id);
    public function setSubject(string $subject, array $parameters = []);
    public function setUser(string $user);
    public function setDateTime(\DateTime $date);
}
