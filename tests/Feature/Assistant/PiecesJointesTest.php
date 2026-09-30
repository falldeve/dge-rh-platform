<?php

use App\Models\Conversation;
use App\Models\PieceJointe;
use App\Support\Bibliotheque\ExtracteurTexte;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

uses(RefreshDatabase::class);

it('relie un message à ses pièces jointes', function () {
    $conv = Conversation::factory()->create();
    $msg = $conv->messages()->create(['role' => 'user', 'contenu' => [['type' => 'text', 'text' => 'Voir pièce']]]);
    $msg->piecesJointes()->create([
        'nom_original' => 'note.pdf', 'chemin' => 'assistant/note.pdf',
        'type_mime' => 'application/pdf', 'taille' => 1234, 'texte_extrait' => 'contenu',
    ]);

    expect($msg->piecesJointes)->toHaveCount(1)
        ->and($msg->piecesJointes->first()->estImage())->toBeFalse();
});

it('extrait le texte d’un fichier xlsx', function () {
    $ss = new Spreadsheet;
    $sheet = $ss->getActiveSheet();
    $sheet->setCellValue('A1', 'Région');
    $sheet->setCellValue('B1', 'Inscrits');
    $sheet->setCellValue('A2', 'Dakar');
    $sheet->setCellValue('B2', '1250000');
    $chemin = sys_get_temp_dir().'/test_'.uniqid().'.xlsx';
    (new Xlsx($ss))->save($chemin);

    $texte = app(ExtracteurTexte::class)->extraire($chemin);
    @unlink($chemin);

    expect($texte)->toContain('Dakar')->and($texte)->toContain('1250000');
});
