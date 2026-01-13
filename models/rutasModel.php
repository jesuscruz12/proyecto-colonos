<?php
// C:\xampp\htdocs\qacrmtaktik\models\rutasModel.php
/**
 * Versiones de Ruta + Operaciones — TAKTIK
 * Tablas: versiones_ruta, ruta_operaciones
 * - DataTables server-side
 * - Hacer vigente (única por entidad)
 * - "Eliminar" = desactivar (vigente=0)
 */

use Illuminate\Database\Capsule\Manager as Capsule;

class rutasModel extends Model
{
    public function __construct()
    {
        parent::__construct();
    }

    // =========================
    // Helpers
    // =========================
    private function i($v): int { return (int)($v ?? 0); }
    private function s($v): string { return trim((string)($v ?? '')); }
    private function now(): string { return date('Y-m-d H:i:s'); }
    private function today(): string { return date('Y-m-d'); }

    private function normEntidadTipo(string $v): string
    {
        $v = $this->s($v);
        return in_array($v, ['parte','subensamble'], true) ? $v : 'parte';
    }

    private function nullIfEmpty($v)
    {
        $v = $this->s($v);
        return $v === '' ? null : $v;
    }

    // =========================
    // DataTables - Versiones
    // =========================
    public function dtList(int $empresaId, array $q): array
    {
        $draw   = $this->i($q['draw'] ?? 1);
        $start  = max(0, $this->i($q['start'] ?? 0));
        $length = $this->i($q['length'] ?? 25);
        if ($length <= 0 || $length > 200) $length = 25;

        $search  = $this->s(($q['search']['value'] ?? ''));

        // filtros UX
        $fTipo    = $this->s($q['f_tipo'] ?? '');      // '', 'parte', 'subensamble'
        $fEntidad = $this->s($q['f_entidad_id'] ?? ''); // '', '123'
        $fVigente = $this->s($q['f_vigente'] ?? '');   // '', '1', '0'

        $base = Capsule::table('versiones_ruta as vr')
            ->leftJoin('usuarios as u', 'u.id', '=', 'vr.creado_por')
            ->where('vr.empresa_id', $empresaId)
            ->select([
                'vr.id',
                'vr.entidad_tipo',
                'vr.entidad_id',
                'vr.version',
                'vr.vigente',
                'vr.fecha_vigencia',
                'vr.notas',
                'vr.creado_por',
                Capsule::raw('COALESCE(u.nombre,"") as creado_por_nombre'),
                Capsule::raw('DATE_FORMAT(vr.creado_en,"%Y-%m-%d %H:%i:%s") as creado_en'),
                Capsule::raw('DATE_FORMAT(vr.actualizado_en,"%Y-%m-%d %H:%i:%s") as actualizado_en'),
                Capsule::raw('(SELECT COUNT(*) FROM ruta_operaciones ro WHERE ro.version_ruta_id = vr.id) as ops_count'),
            ]);

        $total = (int) Capsule::table('versiones_ruta')
            ->where('empresa_id', $empresaId)
            ->count();

        if ($fTipo !== '' && in_array($fTipo, ['parte','subensamble'], true)) {
            $base->where('vr.entidad_tipo', $fTipo);
        }
        if ($fEntidad !== '' && ctype_digit($fEntidad)) {
            $base->where('vr.entidad_id', (int)$fEntidad);
        }
        if ($fVigente !== '' && in_array($fVigente, ['0','1'], true)) {
            $base->where('vr.vigente', (int)$fVigente);
        }

        if ($search !== '') {
            $like = '%'.$search.'%';
            $base->where(function($w) use ($like){
                $w->where('vr.version','like',$like)
                  ->orWhere('vr.entidad_tipo','like',$like)
                  ->orWhere('vr.entidad_id','like',$like)
                  ->orWhere('vr.notas','like',$like);
            });
        }

        $cols = [
            0 => 'vr.id',
            1 => 'vr.entidad_tipo',
            2 => 'vr.entidad_id',
            3 => 'vr.version',
            4 => 'vr.vigente',
            5 => 'ops_count',
            6 => 'vr.creado_en',
        ];

        $orderCol = $this->i($q['order'][0]['column'] ?? 6);
        $orderDir = strtolower($this->s($q['order'][0]['dir'] ?? 'desc')) === 'asc' ? 'asc' : 'desc';
        $orderBy  = $cols[$orderCol] ?? 'vr.creado_en';

        if ($orderBy === 'ops_count') $base->orderByRaw('ops_count '.$orderDir);
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
    // CRUD Versiones
    // =========================
    public function getVersion(int $empresaId, int $id): ?array
    {
        $r = Capsule::table('versiones_ruta')
            ->where('empresa_id', $empresaId)
            ->where('id', $id)
            ->first();

        return $r ? (array)$r : null;
    }

    public function saveVersion(int $empresaId, int $userId, array $in): int
    {
        $id         = $this->i($in['id'] ?? 0);
        $tipo       = $this->normEntidadTipo($in['entidad_tipo'] ?? 'parte');
        $entidadId  = $this->i($in['entidad_id'] ?? 0);
        $version    = $this->s($in['version'] ?? '');
        $vigente    = $this->s($in['vigente'] ?? '0');
        $fechaVig   = $this->nullIfEmpty($in['fecha_vigencia'] ?? null);
        $notas      = $this->nullIfEmpty($in['notas'] ?? null);

        if ($entidadId <= 0) throw new Exception('Entidad ID requerido');
        if ($version === '') throw new Exception('Versión requerida');

        $vigente = ($vigente === '1' || $vigente === 'true') ? 1 : 0;

        // unique (empresa, tipo, entidad, version)
        $dup = Capsule::table('versiones_ruta')
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
                Capsule::table('versiones_ruta')
                    ->where('empresa_id', $empresaId)
                    ->where('id', $id)
                    ->update($data);
            } else {
                $data['creado_por'] = $userId > 0 ? $userId : null;
                $data['creado_en'] = $this->now();
                $id = (int) Capsule::table('versiones_ruta')->insertGetId($data);
            }

            // si viene marcado vigente=1, hacemos vigente exclusivo
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
        $updated = Capsule::table('versiones_ruta')
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
        $vr = Capsule::table('versiones_ruta')
            ->where('empresa_id', $empresaId)
            ->where('id', $id)
            ->first();

        if (!$vr) throw new Exception('Versión no encontrada');

        $tipo = (string)$vr->entidad_tipo;
        $entidadId = (int)$vr->entidad_id;

        Capsule::beginTransaction();
        try {
            // baja todas
            Capsule::table('versiones_ruta')
                ->where('empresa_id', $empresaId)
                ->where('entidad_tipo', $tipo)
                ->where('entidad_id', $entidadId)
                ->update([
                    'vigente' => 0,
                    'actualizado_en' => $this->now(),
                ]);

            // sube esta
            Capsule::table('versiones_ruta')
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
    // Procesos (para ops)
    // =========================
    public function procesosList(int $empresaId): array
    {
        return Capsule::table('procesos')
            ->where('empresa_id', $empresaId)
            ->select('id','nombre','setup_minutos','frecuencia_setup','activo')
            ->orderBy('nombre','asc')
            ->get()->map(fn($r)=>(array)$r)->toArray();
    }

    // =========================
    // DataTables - Operaciones
    // =========================
    public function dtOpsList(int $empresaId, int $versionRutaId, array $q): array
    {
        $draw   = $this->i($q['draw'] ?? 1);
        $start  = max(0, $this->i($q['start'] ?? 0));
        $length = $this->i($q['length'] ?? 25);
        if ($length <= 0 || $length > 200) $length = 25;

        // valida que la versión sea de la empresa
        $ok = Capsule::table('versiones_ruta')
            ->where('empresa_id', $empresaId)
            ->where('id', $versionRutaId)
            ->exists();
        if (!$ok) {
            return ['draw'=>$draw,'recordsTotal'=>0,'recordsFiltered'=>0,'data'=>[]];
        }

        $search = $this->s(($q['search']['value'] ?? ''));

        $base = Capsule::table('ruta_operaciones as ro')
            ->join('procesos as p', 'p.id', '=', 'ro.proceso_id')
            ->where('ro.version_ruta_id', $versionRutaId)
            ->where('p.empresa_id', $empresaId)
            ->select([
                'ro.id',
                'ro.version_ruta_id',
                'ro.proceso_id',
                'ro.secuencia',
                'ro.minutos',
                'ro.segundos',
                'ro.setup_minutos',
                'ro.notas',
                'p.nombre as proceso_nombre',
                'p.frecuencia_setup as proceso_freq',
                'p.activo as proceso_activo',
            ]);

        $total = (int) Capsule::table('ruta_operaciones')
            ->where('version_ruta_id', $versionRutaId)
            ->count();

        if ($search !== '') {
            $like = '%'.$search.'%';
            $base->where(function($w) use ($like){
                $w->where('p.nombre','like',$like)
                  ->orWhere('ro.secuencia','like',$like)
                  ->orWhere('ro.notas','like',$like);
            });
        }

        $cols = [
            0 => 'ro.secuencia',
            1 => 'p.nombre',
            2 => 'ro.minutos',
            3 => 'ro.segundos',
            4 => 'ro.setup_minutos',
        ];
        $orderCol = $this->i($q['order'][0]['column'] ?? 0);
        $orderDir = strtolower($this->s($q['order'][0]['dir'] ?? 'asc')) === 'desc' ? 'desc' : 'asc';
        $orderBy  = $cols[$orderCol] ?? 'ro.secuencia';

        $base->orderBy($orderBy, $orderDir);

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

    public function getOp(int $empresaId, int $id): ?array
    {
        $r = Capsule::table('ruta_operaciones as ro')
            ->join('versiones_ruta as vr','vr.id','=','ro.version_ruta_id')
            ->where('vr.empresa_id', $empresaId)
            ->where('ro.id', $id)
            ->select('ro.*')
            ->first();

        return $r ? (array)$r : null;
    }

    public function saveOp(int $empresaId, array $in): int
    {
        $id      = $this->i($in['id'] ?? 0);
        $vrid    = $this->i($in['version_ruta_id'] ?? 0);
        $pid     = $this->i($in['proceso_id'] ?? 0);
        $sec     = $this->i($in['secuencia'] ?? 0);
        $min     = max(0, $this->i($in['minutos'] ?? 0));
        $seg     = max(0, $this->i($in['segundos'] ?? 0));
        $setup   = max(0, $this->i($in['setup_minutos'] ?? 0));
        $notas   = $this->nullIfEmpty($in['notas'] ?? null);

        if ($vrid <= 0) throw new Exception('Versión ruta inválida');
        if ($pid <= 0) throw new Exception('Proceso requerido');
        if ($sec <= 0) throw new Exception('Secuencia requerida (>0)');

        // valida version pertenece a empresa
        $okVr = Capsule::table('versiones_ruta')->where('empresa_id',$empresaId)->where('id',$vrid)->exists();
        if (!$okVr) throw new Exception('Versión ruta inválida');

        // valida proceso pertenece a empresa
        $okP = Capsule::table('procesos')->where('empresa_id',$empresaId)->where('id',$pid)->exists();
        if (!$okP) throw new Exception('Proceso inválido');

        // unique (version_ruta_id, secuencia)
        $dup = Capsule::table('ruta_operaciones')
            ->where('version_ruta_id', $vrid)
            ->where('secuencia', $sec);
        if ($id > 0) $dup->where('id','<>',$id);
        if ($dup->exists()) throw new Exception('Ya existe esa secuencia en esta ruta');

        $data = [
            'version_ruta_id' => $vrid,
            'proceso_id' => $pid,
            'secuencia' => $sec,
            'minutos' => $min,
            'segundos' => $seg,
            'setup_minutos' => $setup,
            'notas' => $notas,
        ];

        if ($id > 0) {
            Capsule::table('ruta_operaciones')->where('id',$id)->update($data);
            return $id;
        }

        return (int) Capsule::table('ruta_operaciones')->insertGetId($data);
    }

    public function deleteOp(int $empresaId, int $id): void
    {
        // valida por empresa
        $ok = Capsule::table('ruta_operaciones as ro')
            ->join('versiones_ruta as vr','vr.id','=','ro.version_ruta_id')
            ->where('vr.empresa_id', $empresaId)
            ->where('ro.id', $id)
            ->exists();

        if (!$ok) throw new Exception('Operación inválida');

        Capsule::table('ruta_operaciones')->where('id',$id)->delete();
    }
}
