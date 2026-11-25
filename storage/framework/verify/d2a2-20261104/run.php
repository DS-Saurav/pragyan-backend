<?php
// Standalone, scratch-only runner. Never loads .env, Artisan or migration traits.
declare(strict_types=1);

use App\Models\{MediaAsset, MediaAttachment, SchoolEvent, Setting, User};
use App\Services\Media\{MediaLibrary, MediaSchema};
use Illuminate\Config\Repository;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Application;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\{DB, Schema, Storage};
use Illuminate\Validation\ValidationException;

$root = dirname(__DIR__, 4);
require $root.'/vendor/autoload.php';
$migrationFiles = [
    '2026_11_01_000001_create_media_assets_table.php' => 'B5BD570BD5690505D64C6EC8F6E5DFFC2952240A67C75B5A6B8A13DBEA8DBA52',
    '2026_11_01_000002_create_media_attachments_table.php' => 'FA71048C99FF5AA58C3B01D39744D3D67CC3126902198D1FF843D94C0E2BBBA6',
];
$report = ['task'=>'D2-A.2', 'started_at'=>date(DATE_ATOM), 'tests'=>[], 'observations'=>[], 'applied'=>[], 'safety'=>[
    'dotenv_loaded'=>false, 'artisan_used'=>false, 'migration_repository_used'=>false,
    'migration_down_called'=>false, 'drop_privileges_requested'=>false,
    'storage_root'=>__DIR__.'/synthetic-storage',
]];
function persist(): void { global $report; file_put_contents(__DIR__.'/report.json', json_encode($report, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES).PHP_EOL); }
function check(bool $ok, string $message): void { if (!$ok) throw new RuntimeException($message); }
function test(string $name, callable $fn): void {
    global $report;
    try { $fn(); $result = ['name'=>$name,'result'=>'PASS']; }
    catch (Throwable $e) { $result = ['name'=>$name,'result'=>'FAIL','type'=>get_class($e),'message'=>$e instanceof PDOException ? 'Database error '.$e->getCode() : $e->getMessage()]; }
    $report['tests'][] = $result; persist(); echo $result['result'].' '.$name.PHP_EOL;
}
function deny(callable $fn, string $class, ?string $contains = null, ?int $status = null): void {
    try { $fn(); } catch (Throwable $e) {
        check($e instanceof $class, 'Unexpected rejection type: '.get_class($e));
        $message = $e instanceof ValidationException ? json_encode($e->errors()) : $e->getMessage();
        if ($contains !== null) check(str_contains($message, $contains), 'Wrong validation reason: '.$message);
        if ($status !== null) check($e->getStatusCode() === $status, 'Wrong HTTP status');
        return;
    }
    throw new RuntimeException('Expected rejection did not occur');
}
function sqlDeny(callable $fn, int $errno): void {
    try { $fn(); } catch (Illuminate\Database\QueryException $e) { check((int)($e->errorInfo[1] ?? 0) === $errno, 'Unexpected MySQL error code '.($e->errorInfo[1] ?? 'unknown')); return; }
    throw new RuntimeException('Expected database constraint rejection did not occur');
}
function hashCheck(): array {
    global $root, $migrationFiles; $hashes=[];
    foreach ($migrationFiles as $name=>$expected) {
        $hashes[$name] = strtoupper(hash_file('sha256', $root.'/database/migrations/'.$name));
        check($hashes[$name] === $expected, 'Migration file changed: '.$name);
    }
    return $hashes;
}

try {
    $report['migration_hashes_before'] = hashCheck();
    $selfCheck = in_array('--self-check', $argv, true);
    if (!$selfCheck) {
        foreach (['HOST'=>'localhost','PORT'=>'33006','DATABASE'=>'school_scratch_media_d2a2','USERNAME'=>'d2_scratch_provisioner'] as $key=>$expected) {
            check(getenv('D2_SCRATCH_DB_'.$key) === $expected, 'Scratch environment mismatch: '.$key);
        }
        check(is_string(getenv('D2_SCRATCH_DB_PASSWORD')) && getenv('D2_SCRATCH_DB_PASSWORD') !== '', 'Scratch password unavailable');
    }
    $app = new Application($root);
    $app->instance('config', new Repository([
        'app'=>['name'=>'D2 scratch verification','env'=>'testing','locale'=>'en','fallback_locale'=>'en','timezone'=>'UTC','key'=>null],
        'database'=>['default'=>'d2_scratch','connections'=>['d2_scratch'=>[
            'driver'=>'mysql','host'=>'localhost','port'=>33006,'database'=>'school_scratch_media_d2a2',
            'username'=>'d2_scratch_provisioner','password'=>$selfCheck ? '' : getenv('D2_SCRATCH_DB_PASSWORD'),
            'charset'=>'utf8mb4','collation'=>'utf8mb4_unicode_ci','prefix'=>'','strict'=>true,'engine'=>'InnoDB',
            'options'=>[PDO::ATTR_TIMEOUT=>5],
        ]]],
        'permissions'=>require $root.'/config/permissions.php',
        'filesystems'=>['default'=>'media_originals','disks'=>[]],
        'logging'=>['default'=>'null','channels'=>['null'=>['driver'=>'monolog','handler'=>Monolog\Handler\NullHandler::class]]],
    ]));
    Illuminate\Support\Facades\Facade::setFacadeApplication($app);
    foreach ([Illuminate\Filesystem\FilesystemServiceProvider::class, Illuminate\Database\DatabaseServiceProvider::class,
        Illuminate\Translation\TranslationServiceProvider::class, Illuminate\Validation\ValidationServiceProvider::class,
        Illuminate\Auth\AuthServiceProvider::class, App\Providers\AppServiceProvider::class] as $provider) $app->register($provider);
    foreach (['media_originals','media_derivatives','public'] as $disk) {
        $app['config']->set('filesystems.disks.'.$disk, ['driver'=>'local','root'=>__DIR__.'/synthetic-storage/'.$disk,'visibility'=>'private','serve'=>false,'throw'=>true]);
    }
    $app['config']->set('permissions.roles.teacher', ['media.view','media.manage']);
    $app->boot();
    check(extension_loaded('pdo_mysql') && extension_loaded('gd'), 'Required PHP extensions unavailable');
    check(app('validator')->make(['x'=>1], ['x'=>'integer'])->passes(), 'Validator setup failed');
    if ($selfCheck) { echo 'SELF-CHECK PASS: isolated framework boot, validation, GD and migration hashes; no database connection.'.PHP_EOL; exit(0); }

    $identity = (array) DB::selectOne('SELECT DATABASE() AS db, @@hostname AS host, @@port AS port, VERSION() AS version, CURRENT_USER() AS account');
    $report['identity']=$identity;
    check($identity['db']==='school_scratch_media_d2a2' && strcasecmp($identity['host'],'Saurav_Acharya')===0 && (int)$identity['port']===33006 && $identity['version']==='8.0.46' && $identity['account']==='d2_scratch_provisioner@localhost', 'Server identity mismatch. STOP.');
    $grants = array_map(fn($r)=>array_values((array)$r)[0], DB::select('SHOW GRANTS'));
    $report['grants']=$grants;
    $expectedGrants = [
        'GRANT USAGE ON *.* TO `d2_scratch_provisioner`@`localhost`',
        'GRANT SELECT, INSERT, UPDATE, DELETE, CREATE, REFERENCES, INDEX, ALTER, CREATE TEMPORARY TABLES ON `school\\_scratch\\_media\\_d2a2`.* TO `d2_scratch_provisioner`@`localhost`',
    ];
    sort($grants); sort($expectedGrants);
    check($grants === $expectedGrants, 'Permissions differ from approved preliminary report. STOP.');
    $report['active_roles']=array_values((array)DB::selectOne('SELECT CURRENT_ROLE() AS roles'))[0];
    check($report['active_roles']==='NONE', 'Unexpected active roles. STOP.');
    $report['database_defaults']=(array)DB::selectOne('SELECT DEFAULT_CHARACTER_SET_NAME AS charset, DEFAULT_COLLATION_NAME AS collation_name FROM information_schema.SCHEMATA WHERE SCHEMA_NAME = DATABASE()');
    check($report['database_defaults']===['charset'=>'utf8mb4','collation_name'=>'utf8mb4_unicode_ci'], 'Scratch database charset/collation mismatch.');
    $report['tables_before']=DB::select('SHOW FULL TABLES'); persist();
    check($report['tables_before']===[], 'Scratch database already contains tables. No existing data will be modified. STOP.');
    // A second connection is never configured. Reject any query outside the allowlist.
    $allowed = ['users','user_roles','school_events','settings','media_assets','media_attachments'];
    DB::connection()->beforeExecuting(function ($sql) use ($allowed) {
        check(!preg_match('/\b(drop|truncate|grant|revoke|use)\b/i', $sql), 'Forbidden SQL blocked');
        check(!str_contains($sql, ';'), 'Multi-statement SQL blocked');
        check(!preg_match('/(?:`[^`]+`|[a-z_][a-z0-9_]*)\s*\.\s*`?(?:users|user_roles|school_events|settings|media_assets|media_attachments)`?/i', $sql), 'Qualified table reference blocked');
        if (preg_match_all('/\b(?:from|join|into|update|references|table)\s+`?([a-z_][a-z0-9_]*)`?/i', $sql, $matches)) {
            foreach ($matches[1] as $table) check(in_array(strtolower($table), $allowed, true) || (strtolower($table)==='information_schema' && preg_match('/^\s*select\b/i',$sql)), 'Unapproved table blocked: '.$table);
        }
    });
    Schema::create('users', function(Blueprint $t) { $t->id(); $t->string('name'); $t->string('email')->nullable(); $t->timestamps(); });
    Schema::create('user_roles', function(Blueprint $t) { $t->id(); $t->unsignedBigInteger('user_id'); $t->string('role'); $t->timestamps(); });
    Schema::create('school_events', function(Blueprint $t) { $t->id(); $t->string('title'); $t->date('date'); $t->string('type'); $t->text('description')->nullable(); $t->boolean('is_public')->default(false); $t->timestamps(); });
    Schema::create('settings', function(Blueprint $t) { $t->id(); $t->string('key')->unique(); $t->text('value')->nullable(); $t->timestamps(); });
    $report['prerequisite_tables']=['users','user_roles','school_events','settings']; persist();
    foreach ($migrationFiles as $name=>$expected) {
        hashCheck(); $migration = require $root.'/database/migrations/'.$name;
        $migration->up(); $report['applied'][]=$name; persist(); echo 'APPLIED '.$name.PHP_EOL;
    }
    foreach ($allowed as $table) {
        $report['schema'][$table]=[
            'ddl'=>array_values((array)DB::selectOne('SHOW CREATE TABLE `'.$table.'`'))[1],
            'indexes'=>array_map(fn($r)=>(array)$r, DB::select('SHOW INDEX FROM `'.$table.'`')),
        ];
    }
    persist();
    test('Both media tables have every service-required column', fn()=>check(app(MediaSchema::class)->ready(), 'Schema incomplete'));
    test('Asset schema has exactly three SET NULL user foreign keys', function() use (&$report) {
        $ddl=$report['schema']['media_assets']['ddl']; check(substr_count($ddl,'ON DELETE SET NULL')===3, 'Wrong FK deletion rules');
        foreach(['uploaded_by','reviewed_by','published_by'] as $col) check(str_contains($ddl,'FOREIGN KEY (`'.$col.'`) REFERENCES `users` (`id`)'), 'Missing attribution FK');
    });
    test('Attachment asset foreign key protects referenced asset deletion', function() use (&$report) { check(str_contains($report['schema']['media_attachments']['ddl'],'FOREIGN KEY (`media_asset_id`) REFERENCES `media_assets` (`id`) ON DELETE RESTRICT'), 'Missing restrict FK'); });
    test('Required asset unique and lookup indexes exist', function() use (&$report) {
        $groups=[]; foreach($report['schema']['media_assets']['indexes'] as $i) $groups[$i['Key_name']][]=$i;
        foreach(['original_path','public_path','storage_filename','checksum','category','uploaded_by','reviewed_by','published_by','status','archived_at'] as $col) {
            $matching=array_filter($groups, fn($g)=>count($g)===1 && $g[0]['Column_name']===$col);
            check(count($matching)>0,'Missing index '.$col);
            if(in_array($col,['original_path','public_path','storage_filename'])) check((int)array_values($matching)[0][0]['Non_unique']===0,'Missing uniqueness '.$col);
        }
    });
    test('Attachment owner and unique four-column slot indexes exist in order', function() use (&$report) {
        $groups=[]; foreach($report['schema']['media_attachments']['indexes'] as $i) $groups[$i['Key_name']][]=$i;
        check(array_column($groups['media_attachment_owner']??[],'Column_name')===['attachable_type','attachable_id'],'Wrong owner index');
        check(array_column($groups['media_attachment_slot']??[],'Column_name')===['attachable_type','attachable_id','placement','display_order'],'Wrong slot index');
        check((int)$groups['media_attachment_slot'][0]['Non_unique']===0,'Slot not unique');
    });
    $sequence=0;
    $raw = function(array $overrides=[]) use (&$sequence) {
        $n=++$sequence;
        return DB::table('media_assets')->insertGetId(array_replace([
            'original_path'=>'synthetic/raw-'.$n.'.jpg','storage_filename'=>'raw-'.$n.'.jpg','original_filename'=>'synthetic.jpg',
            'mime_type'=>'image/jpeg','file_size'=>100,'width'=>10,'height'=>10,'checksum'=>str_repeat('a',64),
        ],$overrides));
    };
    $a=$raw(); $b=$raw();
    test('Default status and disks, nullable public paths and attribution', function() use($a,$b) {
        $r=DB::table('media_assets')->find($a); check($r->status==='draft' && $r->disk==='media_originals' && $r->public_disk==='media_derivatives','Wrong defaults');
        foreach(['public_path','uploaded_by','reviewed_by','published_by','archived_at'] as $col) check($r->$col===null,'Expected nullable '.$col);
        check(DB::table('media_assets')->whereIn('id',[$a,$b])->whereNull('public_path')->count()===2,'Multiple NULL unique paths rejected');
    });
    foreach(['original_path'=>'synthetic/raw-1.jpg','storage_filename'=>'raw-1.jpg'] as $col=>$value) test('MySQL rejects duplicate '.$col, fn()=>sqlDeny(fn()=>$raw([$col=>$value]),1062));
    $p=$raw(['public_path'=>'synthetic/shared.jpg']);
    test('MySQL rejects duplicate non-null public_path', fn()=>sqlDeny(fn()=>$raw(['public_path'=>'synthetic/shared.jpg']),1062));
    test('Checksum index permits deliberate duplicate content hashes', fn()=>check(DB::table('media_assets')->where('checksum',str_repeat('a',64))->count()>=3,'Checksums incorrectly unique'));
    foreach(['uploaded_by','reviewed_by','published_by'] as $col) test('MySQL rejects missing user FK '.$col, fn()=>sqlDeny(fn()=>$raw([$col=>999999999]),1452));
    foreach(['file_size','width','height'] as $col) test('Strict MySQL rejects negative unsigned '.$col, fn()=>sqlDeny(fn()=>$raw([$col=>-1]),1264));
    $uid=DB::table('users')->insertGetId(['name'=>'D2 synthetic deleted attribution']);
    $attributed=$raw(['uploaded_by'=>$uid,'reviewed_by'=>$uid,'published_by'=>$uid]);
    test('Deleting synthetic user preserves asset and nulls all attribution FKs', function() use($uid,$attributed) { DB::table('users')->where('id',$uid)->delete(); $r=DB::table('media_assets')->find($attributed); check($r!==null && $r->uploaded_by===null && $r->reviewed_by===null && $r->published_by===null,'Attribution not preserved'); });
    $rawAttachment=['media_asset_id'=>$a,'attachable_type'=>'public-event','attachable_id'=>900000001,'placement'=>'raw-slot'];
    $attId=DB::table('media_attachments')->insertGetId($rawAttachment);
    test('MySQL focal defaults are 50.00 and order defaults to zero', function() use($attId) { $r=DB::table('media_attachments')->find($attId); check($r->focal_x==='50.00' && $r->focal_y==='50.00' && (int)$r->display_order===0,'Wrong attachment defaults'); });
    test('MySQL rejects missing media asset FK', fn()=>sqlDeny(fn()=>DB::table('media_attachments')->insert(array_replace($rawAttachment,['media_asset_id'=>999999999,'placement'=>'other'])),1452));
    test('MySQL rejects duplicate owner slot even with a different asset', fn()=>sqlDeny(fn()=>DB::table('media_attachments')->insert(array_replace($rawAttachment,['media_asset_id'=>$b])),1062));
    test('MySQL blocks deletion of attached asset', fn()=>sqlDeny(fn()=>DB::table('media_assets')->where('id',$a)->delete(),1451));
    test('MySQL permits separate orders and placements', function() use($rawAttachment) { DB::table('media_attachments')->insert($rawAttachment+['display_order'=>1]); DB::table('media_attachments')->insert(array_replace($rawAttachment,['placement'=>'different'])); });
    test('Decimal(5,2) round-trips focal boundaries exactly', function() use($attId) { DB::table('media_attachments')->where('id',$attId)->update(['focal_x'=>0,'focal_y'=>100]); $r=DB::table('media_attachments')->find($attId); check($r->focal_x==='0.00' && $r->focal_y==='100.00','Boundary precision mismatch'); });
    test('Document database-only validation boundary using synthetic records', function() use($raw,$attId,&$report) {
        DB::table('media_attachments')->where('id',$attId)->update(['focal_x'=>-1,'focal_y'=>101]);
        $id=$raw(['status'=>'unvalidated','checksum'=>'not-a-sha256','width'=>0]);
        check(DB::table('media_assets')->find($id)->status==='unvalidated','Unexpected DB status constraint');
        $report['observations'][]='Direct SQL accepts out-of-range focal coordinates, arbitrary status, non-SHA256 checksum strings and zero dimensions. These migrations do not define CHECK constraints; service validation is required.';
        $report['observations'][]='Polymorphic owners have no database foreign key. Direct SQL can create references to nonexistent owners; service owner validation and fail-closed reference checks are required.';
    });
    $makeUser=function(string $role) { $id=DB::table('users')->insertGetId(['name'=>'D2 synthetic '.$role]); DB::table('user_roles')->insert(['user_id'=>$id,'role'=>$role]); return User::findOrFail($id); };
    $uploader=$makeUser('teacher'); $reviewer=$makeUser('teacher'); $admin=$makeUser('admin'); $denied=$makeUser('student');
    $lib=app(MediaLibrary::class);
    $image=imagecreatetruecolor(32,24); imagefill($image,0,0,imagecolorallocate($image,40,100,160));
    if(!is_dir(__DIR__.'/synthetic-storage/input')) mkdir(__DIR__.'/synthetic-storage/input',0777,true);
    $input=__DIR__.'/synthetic-storage/input/generated.jpg'; imagejpeg($image,$input,90); imagedestroy($image);
    $upload=fn(User $who)=>$lib->upload($who,new UploadedFile($input,'d2-synthetic.jpg','image/jpeg',null,true),['category'=>'academics','title'=>'D2 synthetic image']);
    test('Unauthorized actor cannot upload', fn()=>deny(fn()=>$upload($denied),Illuminate\Auth\Access\AuthorizationException::class));
    $asset=$upload($uploader);
    test('Service upload records draft dimensions, bytes, SHA256 and private original', function() use($asset,$input) { check($asset->status==='draft' && $asset->width===32 && $asset->height===24,'Wrong upload metadata'); check($asset->checksum===hash_file('sha256',$input) && $asset->file_size===filesize($input),'Wrong checksum or size'); check(Storage::disk('media_originals')->exists($asset->original_path) && $asset->public_path===null,'Original/public storage incorrect'); });
    test('Draft cannot skip directly to publication', fn()=>deny(fn()=>$lib->publish($reviewer,$asset),ValidationException::class,'Review'));
    test('Draft metadata can be edited', function() use($lib,$uploader,$asset) { check($lib->updateMetadata($uploader,$asset,['title'=>'D2 revised'])->title==='D2 revised','Draft edit failed'); });
    test('Draft enters review and clears approval', function() use($lib,$uploader,$asset) { $r=$lib->submit($uploader,$asset); check($r->status==='review' && $r->submitted_at!==null && $r->reviewed_by===null,'Wrong review transition'); });
    test('Review asset metadata edits are blocked', fn()=>deny(fn()=>$lib->updateMetadata($uploader,$asset,['title'=>'Rejected']),ValidationException::class,'drafts'));
    test('Review cannot publish without approval', fn()=>deny(fn()=>$lib->publish($reviewer,$asset),ValidationException::class,'approve'));
    test('Non-admin uploader cannot self-approve', fn()=>deny(fn()=>$lib->approve($uploader,$asset),Symfony\Component\HttpKernel\Exception\HttpException::class,null,403));
    test('Different manager can approve', function() use($lib,$reviewer,$asset) { check($lib->approve($reviewer,$asset)->reviewed_by===$reviewer->id,'Reviewer not recorded'); });
    test('Non-admin uploader cannot self-publish after another review', fn()=>deny(fn()=>$lib->publish($uploader,$asset),Symfony\Component\HttpKernel\Exception\HttpException::class,null,403));
    test('Different manager publishes a derivative and retains original', function() use($lib,$reviewer,&$asset) { $asset=$lib->publish($reviewer,$asset); check($asset->status==='published' && $asset->published_by===$reviewer->id,'Publication failed'); check(Storage::disk('media_derivatives')->exists($asset->public_path) && Storage::disk('media_originals')->exists($asset->original_path),'Missing files'); });
    $event=SchoolEvent::create(['title'=>'D2 synthetic public event','date'=>'2026-11-04','type'=>'event','is_public'=>true]);
    $slot=['attachable_type'=>'public-event','attachable_id'=>$event->id,'placement'=>' Cover ','display_order'=>0];
    $attachment=$lib->attach($reviewer,$asset,$slot);
    test('Service canonicalizes placement and defaults omitted focal values', fn()=>check($attachment->placement==='cover' && $attachment->focal_x==='50.00' && $attachment->focal_y==='50.00','Incorrect canonical values'));
    foreach(['focal_x','focal_y'] as $axis) foreach([-1,101,null,'','NaN','1e2','50.555',true] as $i=>$bad) test('Service rejects '.$axis.' invalid case '.$i, fn()=>deny(fn()=>$lib->attach($reviewer,$asset,array_replace($slot,['display_order'=>50+$i,$axis=>$bad])),ValidationException::class,$axis));
    test('Service permits exact focal boundaries and preserves omitted axis on update', function() use($lib,$reviewer,$attachment) { $r=$lib->updateAttachment($reviewer,$attachment,['focal_x'=>0,'focal_y'=>100]); check($r->focal_x==='0.00' && $r->focal_y==='100.00','Boundary mismatch'); $r=$lib->updateAttachment($reviewer,$attachment,['focal_x'=>'0.29']); check($r->focal_x==='0.29' && $r->focal_y==='100.00','Partial update altered omitted axis'); });
    test('Invalid attachment update preserves all prior metadata', function() use($lib,$reviewer,$attachment) { $before=$attachment->fresh()->getRawOriginal(); deny(fn()=>$lib->updateAttachment($reviewer,$attachment,['focal_x'=>101,'caption'=>'Rejected']),ValidationException::class,'focal_x'); check($attachment->fresh()->getRawOriginal()===$before,'Partial write occurred'); });
    test('Service rejects duplicate canonical owner slot', fn()=>deny(fn()=>$lib->attach($reviewer,$asset,$slot),ValidationException::class,'occupied'));
    test('Service denies unsupported private owner types', fn()=>deny(fn()=>$lib->attach($reviewer,$asset,array_replace($slot,['attachable_type'=>'student'])),ValidationException::class,'attachable_type'));
    test('Service denies nonexistent owner', fn()=>deny(fn()=>$lib->attach($reviewer,$asset,array_replace($slot,['attachable_id'=>999999999])),Illuminate\Database\Eloquent\ModelNotFoundException::class));
    test('Service denies invalid placement', fn()=>deny(fn()=>$lib->attach($reviewer,$asset,array_replace($slot,['placement'=>'bad_slot'])),ValidationException::class,'placement'));
    test('Unauthorized actor cannot update attachment', fn()=>deny(fn()=>$lib->updateAttachment($denied,$attachment,['caption'=>'Rejected']),Illuminate\Auth\Access\AuthorizationException::class));
    test('Public event reference prevents archive even by admin', fn()=>deny(fn()=>$lib->archive($admin,$asset),ValidationException::class,'public-event'));
    test('Public event reference prevents return to draft', fn()=>deny(fn()=>$lib->draft($admin,$asset),ValidationException::class,'public-event'));
    test('Model prohibits asset deletion', fn()=>deny(fn()=>$asset->delete(),LogicException::class,'archived'));
    test('Detached asset can archive, archived selection is blocked, restore returns to draft', function() use($lib,$admin,$asset,$attachment,$slot) { $lib->detach($admin,$attachment); check($lib->archive($admin,$asset)->archived_at!==null,'Archive failed'); deny(fn()=>$lib->attach($admin,$asset,$slot),ValidationException::class,'active published'); $r=$lib->restore($admin,$asset); check($r->status==='draft' && $r->archived_at===null && $r->reviewed_by===null && $r->published_by===null,'Restore failed'); });
    $adminAsset=$upload($admin);
    test('Configured administrator override permits self-review and publication', function() use($lib,$admin,&$adminAsset) { $lib->submit($admin,$adminAsset); $lib->approve($admin,$adminAsset); $adminAsset=$lib->publish($admin,$adminAsset); check($adminAsset->status==='published','Admin override failed'); });
    $hero=Setting::set('website_hero_images','[]');
    $heroAttachment=$lib->attach($admin,$adminAsset,['attachable_type'=>'homepage-hero','attachable_id'=>$hero->id,'placement'=>'hero','display_order'=>0]);
    test('Homepage hero reference prevents archive', fn()=>deny(fn()=>$lib->archive($admin,$adminAsset),ValidationException::class,'homepage-hero'));
    $other=Setting::set('d2_synthetic_other_setting','synthetic');
    test('Non-hero setting cannot own media', fn()=>deny(fn()=>$lib->attach($admin,$adminAsset,['attachable_type'=>'homepage-hero','attachable_id'=>$other->id,'placement'=>'hero','display_order'=>0]),ValidationException::class,'homepage'));
    test('Missing referenced event fails closed against archive', function() use($lib,$admin,$adminAsset) { $e=SchoolEvent::create(['title'=>'D2 deleted owner','date'=>'2026-11-04','type'=>'event','is_public'=>true]); $lib->attach($admin,$adminAsset,['attachable_type'=>'public-event','attachable_id'=>$e->id,'placement'=>'missing-owner','display_order'=>0]); DB::table('school_events')->where('id',$e->id)->delete(); deny(fn()=>$lib->archive($admin,$adminAsset),ValidationException::class,'public-event'); });
    $tampered=$upload($uploader); $lib->submit($uploader,$tampered); $lib->approve($reviewer,$tampered);
    test('Changed original bytes block publication and leave review state', function() use($lib,$reviewer,$tampered) { $im=imagecreatetruecolor(16,16); ob_start(); imagejpeg($im); $bytes=ob_get_clean(); imagedestroy($im); Storage::disk('media_originals')->put($tampered->original_path,$bytes); deny(fn()=>$lib->publish($reviewer,$tampered),ValidationException::class,'changed after upload'); $r=$tampered->fresh(); check($r->status==='review' && $r->public_path===null,'Failed publication modified state'); });
    test('Legacy synthetic hero checksum reference prevents archive without attachments', function() use($lib,$admin,$upload,$hero) { $a=$upload($admin); $lib->submit($admin,$a); $lib->approve($admin,$a); $a=$lib->publish($admin,$a); Storage::disk('public')->put('hero/d2-synthetic.jpg',Storage::disk('media_originals')->get($a->original_path)); Setting::set('website_hero_images',json_encode([['path'=>'hero/d2-synthetic.jpg']])); deny(fn()=>$lib->archive($admin,$a),ValidationException::class,'legacy homepage hero'); Setting::set('website_hero_images','[]'); });
    $report['migration_hashes_after']=hashCheck();
    foreach($allowed as $table) $report['final_row_counts'][$table]=DB::table($table)->count();
    $report['final_table_names']=array_map(fn($r)=>array_values((array)$r)[0],DB::select('SHOW TABLES'));
    foreach(['media_originals','media_derivatives','public'] as $disk) $report['synthetic_files'][$disk]=Storage::disk($disk)->allFiles();
    $report['summary']=['passed'=>count(array_filter($report['tests'],fn($r)=>$r['result']==='PASS')),'failed'=>count(array_filter($report['tests'],fn($r)=>$r['result']==='FAIL'))];
    $report['state']='STOPPED_AT_OWNER_REVIEW_CHECKPOINT';
    $report['limitations']=['No migration rollback or reapplication attempted.','HTTP routes, browser UI and concurrent multi-process locking were not tested.','Minimal prerequisite tables model only columns needed for these tests; this is not full existing-schema compatibility testing.','Synthetic records and files are retained for review. No real media storage was configured.','Migration up() methods were called directly in order; no migration history table or other migration was executed.'];
    persist(); echo json_encode($report['summary']).PHP_EOL.'STOPPED: owner review checkpoint. No migration rollback or DROP.'.PHP_EOL;
} catch(Throwable $e) {
    $report['state']='STOPPED_ON_ERROR';
    $report['error']=['type'=>get_class($e),'code'=>$e->getCode(),'message'=>$e instanceof PDOException ? 'Database operation failed; details withheld to protect credentials.' : $e->getMessage()];
    persist(); echo 'STOPPED: '.$report['error']['message'].PHP_EOL; exit(1);
}
