<?php

declare(strict_types=1);

// File: guinchafacil/src/Controllers/VehicleCatalogController.php
// �CATALOGO-VISUAL-01 (02/08/2026): endpoints JSON P�BLICOS (sem
// autentica��o) que alimentam o autocomplete de marca/modelo - usado tanto
// no cadastro de ve�culo do CLIENTE (autenticado, mas reaproveita o mesmo
// endpoint) quanto no cadastro de CAMINH�O do guincheiro, que acontece
// ANTES do login existir (tela de registro p�blica). Por isso n�o pode
// exigir 'cliente'/'guincho' - � o mesmo cat�logo pros dois.
// S� leitura, s� dados j� marcados `active`, sem nenhum dado sens�vel.

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../Models/Vehicle/VehicleBrand.php';
require_once __DIR__ . '/../Models/Vehicle/VehicleModel.php';

class VehicleCatalogController
{
    /** GET /veiculo-catalogo/marcas - todas as marcas ativas (id, name, logo_path). */
    public function marcas(): void
    {
        $marcas = VehicleBrand::listarAtivas();
        $payload = array_map(static function ($m) {
            $slug = strtolower(trim(preg_replace('/[^a-z0-9]+/i', '-', iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', (string)$m['name']) ?: (string)$m['name']), '-'));            $logoUrl = null;            foreach (['png','svg','jpg','jpeg','webp'] as $ext) {                $file = dirname(__DIR__, 2) . '/public/assets/vehicle-catalog/midia/logos/' . $slug . '.' . $ext;                if (is_file($file)) { $logoUrl = '/public/assets/vehicle-catalog/midia/logos/' . $slug . '.' . $ext; break; }            }            if ($logoUrl === null) foreach (['svg','png','jpg','jpeg','webp'] as $ext) {                $file = dirname(__DIR__, 2) . '/public/assets/img/vehicle-brands/' . $slug . '.' . $ext;                if (is_file($file)) { $logoUrl = '/public/assets/img/vehicle-brands/' . $slug . '.' . $ext; break; }            }            return [
                'id' => (int)$m['id'],
                'name' => (string)$m['name'],
                'logo_path' => $m['logo_path'] ?: $logoUrl,
            ];
        }, $marcas);

        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    /** GET /veiculo-catalogo/modelos?marca_id=X - modelos ativos de UMA marca. */
    public function modelos(): void
    {
        $marcaId = (int)($_GET['marca_id'] ?? 0);
        $modelos = $marcaId > 0 ? VehicleModel::listarPorMarca($marcaId) : [];        $marcaNome = '';        try { $s=getPDO()->prepare('SELECT name FROM vehicle_brands WHERE id=? LIMIT 1'); $s->execute([$marcaId]); $marcaNome=(string)$s->fetchColumn(); } catch (Throwable $e) {}        $slug = static function(string $v): string { $v=iconv('UTF-8','ASCII//TRANSLIT//IGNORE',$v) ?: $v; return trim(preg_replace('/[^a-z0-9]+/i','-',strtolower($v)),'-'); };        $findMedia = static function(string $brand,string $model) use ($slug): ?string { $root=dirname(__DIR__,2).'/public/assets/vehicle-catalog/midia/modelos/'; foreach(['carros','suv','picapes','caminhoes','motocicletas'] as $dir) foreach([$slug($brand.'-'.$model),$slug($model)] as $name) foreach(['jpg','jpeg','png','webp'] as $ext){$file=$root.$dir.'/'.$name.'.'.$ext;if(is_file($file))return '/public/assets/vehicle-catalog/midia/modelos/'.$dir.'/'.$name.'.'.$ext;} return null; };
        $payload = array_map(static function ($mo) {
            return [
                'id' => (int)$mo['id'],
                'name' => (string)$mo['name'],
                'image_path' => $mo['image_path'] ?: null,
            ];
        }, $modelos);

        foreach ($payload as &$item) { if (!$item['image_path']) $item['image_path']=$findMedia($marcaNome,$item['name']); }        unset($item);        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }    public function cidades(): void    {        header('Content-Type: application/json; charset=utf-8');        $uf = strtoupper(trim((string)($_GET['uf'] ?? '')));        if (!preg_match('/^[A-Z]{2}$/', $uf)) { echo json_encode([]); return; }        $cidades = [];        try { $s=getPDO()->prepare('SELECT id,nome FROM cidades WHERE uf=? AND ativo=1 ORDER BY nome'); $s->execute([$uf]); $cidades=$s->fetchAll(PDO::FETCH_ASSOC); } catch (Throwable $e) {}        $url = 'https://servicodados.ibge.gov.br/api/v1/localidades/estados/' . rawurlencode($uf) . '/municipios';        $ctx = stream_context_create(['http'=>['timeout'=>8,'ignore_errors'=>true,'header'=>"Accept: application/json\r\n"]]);        $raw = @file_get_contents($url, false, $ctx);        $ibge = json_decode($raw ?: '[]', true);        $porNome = [];        foreach ($cidades as $cidade) { $nome=(string)($cidade['nome'] ?? ''); if ($nome !== '') $porNome[mb_strtolower($nome,'UTF-8')] = ['id'=>(int)($cidade['id'] ?? 0),'nome'=>$nome]; }        if (is_array($ibge)) foreach ($ibge as $cidade) { $nome=(string)($cidade['nome'] ?? ''); if ($nome !== '') { $key=mb_strtolower($nome,'UTF-8'); if (!isset($porNome[$key])) $porNome[$key]=['id'=>(int)($cidade['id'] ?? 0),'nome'=>$nome]; } }        uasort($porNome, static fn($a,$b)=>strcoll((string)$a['nome'],(string)$b['nome']));        echo json_encode(array_values($porNome), JSON_UNESCAPED_UNICODE);    }
}
