<?php
declare(strict_types=1);
/** Ponte local HTTP -> CLI. Nao envia pagamentos; permite somente operacoes administrativas. */
final class GFPayoutHttpExecutor
{
    private const MODES=['review_inspect','review_create','decision_read','decision_apply','recovery_read','recovery_queue','wallet_report'];
    public static function run(array $job):array
    {
        $process=null;$dir=null;$started=false;$phase='VALIDATE';$status=[];
        try{
            if(!in_array($job['mode']??null,self::MODES,true) || !is_int($job['actor']??null) || $job['actor']<1)throw new InvalidArgumentException('M1_OPERATION_INVALID');
            $root=dirname(__DIR__,3);
            // PHP_BINARY sob Apache pode apontar ao host do modulo, nao ao CLI homologado.
            $php=function_exists('env')?env('GF_FIN_PHP_EXE',''):getenv('GF_FIN_PHP_EXE');
            if(!$php)$php=PHP_OS_FAMILY==='Windows'?'C:\\xampp\\php84\\php.exe':(PHP_SAPI==='cli'||PHP_SAPI==='cli-server'?PHP_BINARY:'');
            if(!$php || !is_file($php))throw new RuntimeException('M1_PHP_CLI_NOT_CONFIGURED');
            $php=realpath($php);$worker=$root.'/bin/gf_payout_http_worker.php';
            if(!is_file($worker))throw new RuntimeException('M1_WORKER_MISSING');
            $disabled='curl_exec,curl_multi_exec,fsockopen,pfsockopen,stream_socket_client,mail,exec,shell_exec,system,passthru,popen,proc_open';
            $cmd=[$php];$ini=php_ini_loaded_file();
            if($ini && PHP_SAPI!=='apache2handler'){$cmd[]='-c';$cmd[]=$ini;}
            // No Apache o CLI usa o php.ini da propria instalacao, sem herdar o do PHP antigo.
            array_push($cmd,'-d','allow_url_fopen=0','-d','disable_functions='.$disabled,'-d','display_errors=0','-d','log_errors=1',$worker);
            if(function_exists('set_time_limit') && !set_time_limit(40))throw new RuntimeException('M1_PARENT_TIME_LIMIT_UNAVAILABLE');
            $encoded=json_encode($job,JSON_THROW_ON_ERROR);if(strlen($encoded)>16384)throw new RuntimeException('M1_INPUT_TOO_LARGE');
            $dir=sys_get_temp_dir().DIRECTORY_SEPARATOR.'gf_m1_'.bin2hex(random_bytes(16));
            if(!mkdir($dir,0700))throw new RuntimeException('M1_PRIVATE_DIRECTORY_FAILED');
            $input=$dir.'/input.json';$output=$dir.'/stdout.json';$errors=$dir.'/stderr.log';
            $f=fopen($input,'x');if(!$f)throw new RuntimeException('M1_INPUT_OPEN_FAILED');
            try{if(fwrite($f,$encoded)!==strlen($encoded) || !fflush($f))throw new RuntimeException('M1_INPUT_WRITE_FAILED');}finally{fclose($f);}
            chmod($input,0600);$phase='START_CHILD';
            // Arquivos privados evitam pipes bloqueantes do Windows anularem o timeout.
            $process=proc_open($cmd,[0=>['file',$input,'r'],1=>['file',$output,'w'],2=>['file',$errors,'w']],$pipes,$root,null,['bypass_shell'=>true]);
            if(!is_resource($process))throw new RuntimeException('M1_CHILD_START_FAILED');$started=true;
            $begin=microtime(true);$phase='WAIT_CHILD';
            do{
                clearstatcache();
                if((is_file($output) && filesize($output)>1048576) || (is_file($errors) && filesize($errors)>65536))throw new RuntimeException('M1_CHILD_OUTPUT_LIMIT');
                $status=proc_get_status($process);if(!$status['running'])break;
                if(microtime(true)-$begin>=35.0)throw new RuntimeException('M1_CHILD_TIMEOUT_OUTCOME_UNKNOWN');
                usleep(20000);
            }while(true);
            $closed=proc_close($process);$process=null;$exit=(int)($status['exitcode']??$closed);if($exit<0)$exit=$closed;
            $stdout=is_file($output)?file_get_contents($output):'';
            if($exit!==0){$bad=json_decode($stdout,true);if(is_array($bad) && is_array($bad['diagnostic']??null))error_log(json_encode($bad['diagnostic'],JSON_INVALID_UTF8_SUBSTITUTE));throw new RuntimeException('M1_CHILD_EXIT_'.$exit);}
            $phase='DECODE_RESULT';$r=json_decode($stdout,true,512,JSON_THROW_ON_ERROR);
            if(!is_array($r) || array_is_list($r) || !is_bool($r['ok']??null) || !is_int($r['http']??null) || !in_array($r['http'],[200,201,403,404,409,422,500,503],true))throw new RuntimeException('M1_CHILD_INVALID_RESULT');
            // Diagnosticos ficam no log do servidor. Nunca em resposta operacional.
            if(isset($r['diagnostic'])){$r['diagnostic']['remaining_percent']=30;error_log(json_encode($r['diagnostic'],JSON_INVALID_UTF8_SUBSTITUTE));unset($r['diagnostic']);}
            return $r;
        }catch(Throwable $e){
            $d=['project'=>'GuinchaFacil','class'=>self::class,'function'=>'run','phase'=>$phase,'status'=>'FAIL','error'=>$e->getMessage(),'file'=>basename($e->getFile()),'line'=>$e->getLine(),'outcome_unknown'=>$started,'remaining_percent'=>30];error_log(json_encode($d,JSON_INVALID_UTF8_SUBSTITUTE));
            return ['ok'=>false,'http'=>503,'erro'=>$started?'resultado_indeterminado_consulte':'executor_financeiro_indisponivel'];
        }finally{
            if(is_resource($process)){
                $s=proc_get_status($process);if($s['running']){proc_terminate($process);$until=microtime(true)+0.5;do{usleep(20000);$s=proc_get_status($process);}while($s['running'] && microtime(true)<$until);if($s['running'])proc_terminate($process,9);}
                proc_close($process);
            }
            if($dir!==null){foreach(['input.json','stdout.json','stderr.log'] as $name)if(is_file($dir.'/'.$name))unlink($dir.'/'.$name);if(is_dir($dir))rmdir($dir);}
        }
    }
}
