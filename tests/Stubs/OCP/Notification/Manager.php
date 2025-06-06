<?php
namespace OCP\Notification;

class Manager implements IManager {
    public function createNotification() {
        return new Notification();
    }
    public function markProcessed(INotification $notification) {}
    public function notify(INotification $notification) {}
}
