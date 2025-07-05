<?php
interface FormatInterface {
    public function import(string $filePath, string $lang, PDO $db): array;
    public function export(string $lang, PDO $db): string;
    public static function mimeType(): string;
    public static function fileExtension(): string;
    public static function name(): string;
    public static function menu(): array;
}