<?php
namespace OCP;

interface IDateTimeFormatter {
	public function formatDate(int $timestamp, string $format);
	public function formatTime(int $timestamp, string $format);
}