<?php

/**
 *  This file is part of GestOre
 *  @author     OpenAI Codex
 *  @copyright  (C) 2026
 *  @license    GPL-3.0+ <https://www.gnu.org/licenses/gpl-3.0.html>
 */

function gestoreStudentPhotoDir(): string
{
    return __DIR__ . '/../didattica/uploads/studenti_foto';
}

function gestoreStudentPhotoFileName(int $studentId): string
{
    return 'studente_' . $studentId . '.jpg';
}

function gestoreStudentPhotoPath(int $studentId): string
{
    return gestoreStudentPhotoDir() . '/' . gestoreStudentPhotoFileName($studentId);
}

function gestoreEnsureStudentPhotoDir(): bool
{
    $dir = gestoreStudentPhotoDir();
    return is_dir($dir) || mkdir($dir, 0775, true);
}

function gestoreStudentPhotoUrl(int $studentId, string $prefix = ''): string
{
    $path = gestoreStudentPhotoPath($studentId);
    if (!is_file($path)) {
        return '';
    }

    $prefix = rtrim($prefix, '/');
    $base = ($prefix !== '' ? $prefix . '/' : '') . 'uploads/studenti_foto/' . rawurlencode(gestoreStudentPhotoFileName($studentId));
    return $base . '?v=' . filemtime($path);
}
