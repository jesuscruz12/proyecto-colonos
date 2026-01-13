<?php
// C:\xampp\htdocs\qacrmtaktik\models\bomModel.php
/**
 * BOM — TAKTIK
 * Tablas: versiones_bom, bom_componentes, partes, subensambles
 * - Versiones BOM DataTables server-side
 * - Componentes BOM DataTables server-side
 * - Hacer vigente (única por entidad)
 * - "Eliminar" versión = desactivar (vigente=0)
 */

use Illuminate\Database\Capsule\Manager as Capsule;

class bomModel extends Model
{
    public function __construct()
    {
        parent::__construct();
    }

    // =========================
    // Helpers
    // =========================
    private function i($v): int { return (int)($v ?? 0); }
    private function f($v): float { return (float)($v ?? 0); }
    private function s($v): string { return trim((string)($v ?? '')); }
    private function now(): string { return date('Y-m-d H:i:s'); }
    private function today(): string { return date('Y-m-d'); }

    private function nullIfEmpty($v)
    {
        $v = $this->s($v);
        return $v === '' ? null : $v;
    }

    private function normEntidadTipo(string $v): string
    {
        $v = $this->s($v);
        return in_array($v, ['producto','subensamble'], true) ? $v : 'producto';
    }

    private function normCompTipo(string $v): string
    {
        $v = $this->s($v);
        return in_array($v, ['parte','subensamble'], true) ? $v : 'parte';
    }

    // =========================
    // DataTables - Versiones BOM
    // =========================
    public function dtList(int $empresaId, array $q): array
    {
        $draw   = $this->i($q['draw'] ?? 1);
        $start  = max(0, $this->i($q['start'] ?? 0));
        $length = $this->i($q['length'] ?? 25);
        if ($length <= 0 || $length > 200) $length = 25;

        $search  = $this->s(($q['search']['value'] ?? ''));

        // filtros UX
        $fTipo    = $this->s($q['f_tipo'] ?? '');       // '', 'producto', 'subensamble'
        $fEntidad = $this->s($q['f_entidad_id'] ?? ''); // '', '123'
        $fVigente = $this->s($q['f_vigente'] ?? '');    // '', '1', '0'

        $base = Capsule::table('versiones_bom as vb')
            ->leftJoin('usuarios as u', 'u.id', '=', 'vb.creado_por')
            ->where('vb.empresa_id', $empresaId)
            ->select([
                'vb.id',
                'vb.entidad_tipo',
                'vb.entidad_id',
                'vb.version',
                'vb.vigente',
                'vb.fecha_vigencia',
                'vb.notas',
                'vb.creado_por',
                Capsule::raw('COALESCE(u.nombre,"") as creado_por_nombre'),
                Capsule::raw('DATE_FORMAT(vb.creado_en,"%Y-%m-%d %H:%i:%s") as creado_en'),
                Capsule::raw('DATE_FORMAT(vb.actualizado_en,"%Y-%m-%d %H:%i:%s") as actualizado_en'),
                Capsule::raw('(SELECT COUNT(*) FROM bom_componentes bc WHERE bc.version_bom_id = vb.id) as comps_count'),
            ]);

        $total = (int) Capsule::table('versiones_bom')
            ->where('empresa_id', $empresaId)
            ->count();

        if ($fTipo !== '' && in_array($fTipo, ['producto','subensamble'], true)) {
            $base->where('vb.entidad_tipo', $fTipo);
        }
        if ($fEntidad !== '' && ctype_digit($fEntidad)) {
            $base->where('vb.entidad_id', (int)$fEntidad);
        }
        if ($fVigente !== '' && in_array($fVigente, ['0','1'], true)) {
            $base->where('vb.vigente', (int)$fVigente);
        }

        if ($search !== '') {
            $like = '%'.$search.'%';
            $base->where(function($w) use ($like){
                $w->where('vb.version','like',$like)
                  ->orWhere('vb.entidad_tipo','like',$like)
                  ->orWhere('vb.entidad_id','like',$like)
                  ->orWhere('vb.notas','like',$like);
            });
        }

        $cols = [
            0 => 'vb.id',
            1 => 'vb.entidad_tipo',
            2 => 'vb.entidad_id',
            3 => 'vb.version',
            4 => 'vb.vigente',
            5 => 'comps_count',
            6 => 'vb.fecha_vigencia',
            7 => 'vb.creado_en',
        ];

        $orderCol = $this->i($q['order'][0]['column'] ?? 7);
        $orderDir = strtolower($this->s($q['order'][0]['dir'] ?? 'desc')) === 'asc' ? 'asc' : 'desc';
        $orderBy  = $cols[$orderCol] ?? 'vb.creado_en';

        if ($orderBy === 'comps_count') $base->orderByRaw('comps_count '.$orderDir);
        else $base->orderBy($orderBy, $orderDir);

        $filtered = (int)(clone $base)->count();

        $rows = $base->offset($start)->limit($length)->get()
            ->map(fn($r)=>(array)$r)->toArray();

        return [
            'draw' => $draw,
            'recordsTotal' => $total,
            'recordsFiltered' => $filtered,
            'data' => $rows
        ];
    }

    // =========================
    // CRUD Versiones BOM
    // =========================
    public function getVersion(int $empresaId, int $id): ?array
    {
        $r = Capsule::table('versiones_bom')
            ->where('empresa_id', $empresaId)
            ->where('id', $id)
            ->first();
        return $r ? (array)$r : null;
    }

    public function saveVersion(int $empresaId, int $userId, array $in): int
    {
        $id         = $this->i($in['id'] ?? 0);
        $tipo       = $this->normEntidadTipo($in['entidad_tipo'] ?? 'producto');
        $entidadId  = $this->i($in['entidad_id'] ?? 0);
        $version    = $this->s($in['version'] ?? '');
        $vigente    = $this->s($in['vigente'] ?? '0');
        $fechaVig   = $this->nullIfEmpty($in['fecha_vigencia'] ?? null);
        $notas      = $this->nullIfEmpty($in['notas'] ?? null);

        if ($entidadId <= 0) throw new Exception('Entidad ID requerido');
        if ($version === '') throw new Exception('Versión requerida');

        $vigente = ($vigente === '1' || $vigente === 'true') ? 1 : 0;

        // unique (empresa, tipo, entidad, version)
        $dup = Capsule::table('versiones_bom')
            ->where('empresa_id', $empresaId)
            ->where('entidad_tipo', $tipo)
            ->where('entidad_id', $entidadId)
            ->where('version', $version);

        if ($id > 0) $dup->where('id','<>',$id);
        if ($dup->exists()) throw new Exception('Ya existe esa versión para esa entidad');

        $data = [
            'empresa_id' => $empresaId,
            'entidad_tipo' => $tipo,
            'entidad_id' => $entidadId,
            'version' => $version,
            'vigente' => $vigente,
            'fecha_vigencia' => $fechaVig,
            'notas' => $notas,
            'actualizado_en' => $this->now(),
        ];

        Capsule::beginTransaction();
        try {
            if ($id > 0) {
                Capsule::table('versiones_bom')
                    ->where('empresa_id', $empresaId)
                    ->where('id', $id)
                    ->update($data);
            } else {
                $data['creado_por'] = $userId > 0 ? $userId : null;
                $data['creado_en'] = $this->now();
                $id = (int) Capsule::table('versiones_bom')->insertGetId($data);
            }

            if ($vigente === 1) {
                $this->makeVigente($empresaId, $id, $fechaVig ?: $this->today());
            }

            Capsule::commit();
            return (int)$id;
        } catch(Throwable $e) {
            Capsule::rollBack();
            throw $e;
        }
    }

    public function deactivateVersion(int $empresaId, int $id): void
    {
        $updated = Capsule::table('versiones_bom')
            ->where('empresa_id', $empresaId)
            ->where('id', $id)
            ->update([
                'vigente' => 0,
                'actualizado_en' => $this->now(),
            ]);

        if (!$updated) throw new Exception('No se pudo desactivar');
    }

    public function makeVigente(int $empresaId, int $id, string $fechaVigencia): void
    {
        $vb = Capsule::table('versiones_bom')
            ->where('empresa_id', $empresaId)
            ->where('id', $id)
            ->first();

        if (!$vb) throw new Exception('Versión no encontrada');

        $tipo = (string)$vb->entidad_tipo;
        $entidadId = (int)$vb->entidad_id;

        Capsule::beginTransaction();
        try {
            Capsule::table('versiones_bom')
                ->where('empresa_id', $empresaId)
                ->where('entidad_tipo', $tipo)
                ->where('entidad_id', $entidadId)
                ->update([
                    'vigente' => 0,
                    'actualizado_en' => $this->now(),
                ]);

            Capsule::table('versiones_bom')
                ->where('empresa_id', $empresaId)
                ->where('id', $id)
                ->update([
                    'vigente' => 1,
                    'fecha_vigencia' => $fechaVigencia,
                    'actualizado_en' => $this->now(),
                ]);

            Capsule::commit();
        } catch(Throwable $e) {
            Capsule::rollBack();
            throw $e;
        }
    }

    // =========================
    // Catálogos para componentes
    // =========================
    public function partesList(int $empresaId): array
    {
        return Capsule::table('partes')
            ->where('empresa_id', $empresaId)
            ->select('id','numero','descripcion','unidad','activo')
            ->orderBy('numero','asc')
            ->get()->map(fn($r)=>(array)$r)->toArray();
    }

    public function subensamblesList(int $empresaId): array
    {
        return Capsule::table('subensambles')
            ->where('empresa_id', $empresaId)
            ->select('id','nombre','descripcion','activo')
            ->orderBy('nombre','asc')
            ->get()->map(fn($r)=>(array)$r)->toArray();
    }

    // =========================
    // DataTables - Componentes
    // =========================
    public function dtCompList(int $empresaId, int $versionBomId, array $q): array
    {
        $draw   = $this->i($q['draw'] ?? 1);
        $start  = max(0, $this->i($q['start'] ?? 0));
        $length = $this->i($q['length'] ?? 25);
        if ($length <= 0 || $length > 200) $length = 25;

        // valida VB pertenece empresa
        $ok = Capsule::table('versiones_bom')
            ->where('empresa_id', $empresaId)
            ->where('id', $versionBomId)
            ->exists();
        if (!$ok) return ['draw'=>$draw,'recordsTotal'=>0,'recordsFiltered'=>0,'data'=>[]];

        $search = $this->s(($q['search']['value'] ?? ''));

        $base = Capsule::table('bom_componentes as bc')
            ->where('bc.version_bom_id', $versionBomId)
            ->leftJoin('partes as p','p.id','=','bc.parte_id')
            ->leftJoin('subensambles as s','s.id','=','bc.subensamble_id')
            ->select([
                'bc.id',
                'bc.version_bom_id',
                'bc.componente_tipo',
                'bc.parte_id',
                'bc.subensamble_id',
                'bc.cantidad',
                'bc.merma_pct',
                Capsule::raw('DATE_FORMAT(bc.creado_en,"%Y-%m-%d %H:%i:%s") as creado_en'),
                Capsule::raw('COALESCE(p.numero,"") as parte_numero'),
                Capsule::raw('COALESCE(p.descripcion,"") as parte_desc'),
                Capsule::raw('COALESCE(p.unidad,"") as parte_unidad'),
                Capsule::raw('COALESCE(s.nombre,"") as sub_nombre'),
                Capsule::raw('COALESCE(s.descripcion,"") as sub_desc'),
            ]);

        $total = (int) Capsule::table('bom_componentes')
            ->where('version_bom_id', $versionBomId)
            ->count();

        if ($search !== '') {
            $like = '%'.$search.'%';
            $base->where(function($w) use ($like){
                $w->where('bc.componente_tipo','like',$like)
                  ->orWhere('p.numero','like',$like)
                  ->orWhere('p.descripcion','like',$like)
                  ->orWhere('s.nombre','like',$like)
                  ->orWhere('s.descripcion','like',$like);
            });
        }

        $cols = [
            0 => 'bc.id',
            1 => 'bc.componente_tipo',
            2 => 'bc.cantidad',
            3 => 'bc.merma_pct',
            4 => 'bc.creado_en',
        ];

        $orderCol = $this->i($q['order'][0]['column'] ?? 0);
        $orderDir = strtolower($this->s($q['order'][0]['dir'] ?? 'desc')) === 'asc' ? 'asc' : 'desc';
        $orderBy  = $cols[$orderCol] ?? 'bc.id';

        $base->orderBy($orderBy, $orderDir);

        $filtered = (int)(clone $base)->count();

        $rows = $base->offset($start)->limit($length)->get()
            ->map(fn($r)=>(array)$r)->toArray();

        return [
            'draw'=>$draw,
            'recordsTotal'=>$total,
            'recordsFiltered'=>$filtered,
            'data'=>$rows
        ];
    }

    // =========================
    // CRUD Componentes
    // =========================
    public function getComp(int $empresaId, int $id): ?array
    {
        $r = Capsule::table('bom_componentes as bc')
            ->join('versiones_bom as vb','vb.id','=','bc.version_bom_id')
            ->where('vb.empresa_id', $empresaId)
            ->where('bc.id', $id)
            ->select('bc.*')
            ->first();

        return $r ? (array)$r : null;
    }

    public function saveComp(int $empresaId, array $in): int
    {
        $id      = $this->i($in['id'] ?? 0);
        $vbId    = $this->i($in['version_bom_id'] ?? 0);
        $tipo    = $this->normCompTipo($in['componente_tipo'] ?? 'parte');

        $parteId = $this->i($in['parte_id'] ?? 0);
        $subId   = $this->i($in['subensamble_id'] ?? 0);

        $cantidad = $this->f($in['cantidad'] ?? 1);
        $merma    = $this->f($in['merma_pct'] ?? 0);

        if ($vbId <= 0) throw new Exception('Versión BOM inválida');
        if ($cantidad <= 0) throw new Exception('Cantidad debe ser > 0');
        if ($merma < 0) $merma = 0;

        // valida VB empresa
        $ok = Capsule::table('versiones_bom')->where('empresa_id',$empresaId)->where('id',$vbId)->exists();
        if (!$ok) throw new Exception('Versión BOM inválida');

        if ($tipo === 'parte') {
            if ($parteId <= 0) throw new Exception('Parte requerida');
            $okP = Capsule::table('partes')->where('empresa_id',$empresaId)->where('id',$parteId)->exists();
            if (!$okP) throw new Exception('Parte inválida');
            $subId = 0;
        } else {
            if ($subId <= 0) throw new Exception('Subensamble requerido');
            $okS = Capsule::table('subensambles')->where('empresa_id',$empresaId)->where('id',$subId)->exists();
            if (!$okS) throw new Exception('Subensamble inválido');
            $parteId = 0;
        }

        $data = [
            'version_bom_id' => $vbId,
            'componente_tipo' => $tipo,
            'parte_id' => $parteId ?: null,
            'subensamble_id' => $subId ?: null,
            'cantidad' => number_format($cantidad, 4, '.', ''),
            'merma_pct' => number_format($merma, 3, '.', ''),
        ];

        if ($id > 0) {
            // valida por empresa
            $okId = Capsule::table('bom_componentes as bc')
                ->join('versiones_bom as vb','vb.id','=','bc.version_bom_id')
                ->where('vb.empresa_id',$empresaId)
                ->where('bc.id',$id)
                ->exists();
            if (!$okId) throw new Exception('Componente inválido');

            Capsule::table('bom_componentes')->where('id',$id)->update($data);
            return $id;
        }

        $data['creado_en'] = $this->now();
        return (int) Capsule::table('bom_componentes')->insertGetId($data);
    }

    public function deleteComp(int $empresaId, int $id): void
    {
        $ok = Capsule::table('bom_componentes as bc')
            ->join('versiones_bom as vb','vb.id','=','bc.version_bom_id')
            ->where('vb.empresa_id', $empresaId)
            ->where('bc.id', $id)
            ->exists();
        if (!$ok) throw new Exception('Componente inválido');

        Capsule::table('bom_componentes')->where('id',$id)->delete();
    }
}
