<?php
namespace OCP\Notification;

class Notification implements INotification {
    public function setApp(string $app) { return $this; }
    public function setObject(string $object, int $id) { return $this; }
    public function setSubject(string $subject, array $parameters = []) { return $this; }
    public function setUser(string $user) { return $this; }
    public function setDateTime(\DateTime $date) { return $this; }
}
