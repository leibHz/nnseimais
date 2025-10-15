<?php
// ARQUIVO: api/status_logic.php (VERSÃO MELHORADA COM DETECÇÃO DE FERIADOS)
// Centraliza a lógica para determinar se a loja está aberta ou fechada.

/**
 * Verifica se a data atual é um feriado nacional brasileiro.
 * Usa cache em arquivo para evitar múltiplas requisições à API.
 * 
 * @return bool True se for feriado, False caso contrário
 */
function ehFeriado() {
    date_default_timezone_set('America/Sao_Paulo');
    $hoje = new DateTime();
    $ano_atual = $hoje->format('Y');
    $data_hoje = $hoje->format('Y-m-d');
    
    // Caminho do arquivo de cache
    $cache_file = __DIR__ . '/../cache/feriados_' . $ano_atual . '.json';
    $cache_dir = dirname($cache_file);
    
    // Cria diretório de cache se não existir
    if (!is_dir($cache_dir)) {
        mkdir($cache_dir, 0755, true);
    }
    
    $feriados = [];
    
    // Verifica se o cache existe e é válido (menos de 30 dias)
    if (file_exists($cache_file)) {
        $cache_age = time() - filemtime($cache_file);
        $cache_valid = $cache_age < (30 * 24 * 60 * 60); // 30 dias
        
        if ($cache_valid) {
            $feriados = json_decode(file_get_contents($cache_file), true);
        }
    }
    
    // Se o cache não existe ou está vencido, busca da API
    if (empty($feriados)) {
        try {
            // Usa a Brasil API - API pública e gratuita para feriados nacionais
            $api_url = "https://brasilapi.com.br/api/feriados/v1/{$ano_atual}";
            
            $ch = curl_init($api_url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 5); // Timeout de 5 segundos
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            $response = curl_exec($ch);
            $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            if ($httpcode === 200 && $response) {
                $feriados_data = json_decode($response, true);
                
                // Extrai apenas as datas dos feriados
                $feriados = array_map(function($feriado) {
                    return $feriado['date'];
                }, $feriados_data);
                
                // Salva no cache
                file_put_contents($cache_file, json_encode($feriados, JSON_PRETTY_PRINT));
            }
        } catch (Exception $e) {
            // Em caso de erro na API, continua sem lista de feriados
            error_log("Erro ao buscar feriados: " . $e->getMessage());
        }
    }
    
    // Verifica se hoje é feriado
    return in_array($data_hoje, $feriados);
}

/**
 * Obtém informações sobre o próximo dia de abertura da loja
 * 
 * @param object $config Configurações da loja
 * @return array Array com 'data' e 'horario' do próximo dia útil
 */
function obterProximaAbertura($config) {
    date_default_timezone_set('America/Sao_Paulo');
    $data_teste = new DateTime();
    $data_teste->modify('+1 day'); // Começa testando amanhã
    
    $dias_nomes = [
        1 => 'Segunda-feira',
        2 => 'Terça-feira', 
        3 => 'Quarta-feira',
        4 => 'Quinta-feira',
        5 => 'Sexta-feira',
        6 => 'Sábado',
        7 => 'Domingo'
    ];
    
    // Testa os próximos 14 dias para encontrar um dia útil
    for ($i = 0; $i < 14; $i++) {
        $dia_semana = (int)$data_teste->format('N');
        $data_formatada = $data_teste->format('Y-m-d');
        
        // Verifica se é feriado
        $cache_file = __DIR__ . '/../cache/feriados_' . $data_teste->format('Y') . '.json';
        $eh_feriado = false;
        
        if (file_exists($cache_file)) {
            $feriados = json_decode(file_get_contents($cache_file), true);
            $eh_feriado = in_array($data_formatada, $feriados);
        }
        
        // Se não é domingo e não é feriado
        if ($dia_semana != 7 && !$eh_feriado) {
            $horario = null;
            
            if ($dia_semana >= 1 && $dia_semana <= 5) { // Seg-Sex
                $horario = $config->horario_seg_sex;
            } elseif ($dia_semana == 6) { // Sábado
                $horario = $config->horario_sab;
            }
            
            // Se tem horário definido e não está "Fechado"
            if ($horario && strtolower(trim($horario)) !== 'fechado' && strpos($horario, ' - ')) {
                list($horaAbre, $horaFecha) = array_map('trim', explode(' - ', $horario));
                
                return [
                    'data' => $data_teste->format('d/m/Y'),
                    'dia_semana' => $dias_nomes[$dia_semana],
                    'horario' => $horario,
                    'hora_abre' => $horaAbre
                ];
            }
        }
        
        $data_teste->modify('+1 day');
    }
    
    // Se não encontrou nenhum dia útil nos próximos 14 dias (improvável)
    return [
        'data' => 'Em breve',
        'dia_semana' => '',
        'horario' => 'Consulte a loja',
        'hora_abre' => ''
    ];
}

/**
 * Calcula o status atual da loja com base nas configurações do banco de dados.
 *
 * @param object $config O objeto de configurações vindo da tabela 'configuracoes_site'.
 * @return array Um array contendo 'status_real' (bool), 'mensagem_real' (string) e 'proxima_abertura' (array)
 */
function calcularStatusLoja($config) {
    date_default_timezone_set('America/Sao_Paulo');

    // PRIORIDADE 1: Status manual forçado
    if (isset($config->status_manual) && in_array($config->status_manual, ['aberto', 'fechado'])) {
        $estaAberto = $config->status_manual === 'aberto';
        
        if ($estaAberto) {
            return [
                'status_real' => true,
                'mensagem_real' => 'Loja aberta para encomendas!',
                'proxima_abertura' => null
            ];
        } else {
            $proxima = obterProximaAbertura($config);
            $mensagem = $config->mensagem_status ?: 'Fechado no momento';
            
            // Adiciona informação sobre próxima abertura
            if ($proxima['data'] !== 'Em breve') {
                $mensagem .= " • Abrimos {$proxima['dia_semana']} ({$proxima['data']}) às {$proxima['hora_abre']}";
            }
            
            return [
                'status_real' => false,
                'mensagem_real' => $mensagem,
                'proxima_abertura' => $proxima
            ];
        }
    }

    // PRIORIDADE 2: Lógica automática baseada no horário
    $agora = new DateTime();
    $diaDaSemana = (int)$agora->format('N'); // 1 (Segunda) a 7 (Domingo)
    
    // VERIFICAÇÃO 1: É domingo?
    if ($diaDaSemana == 7) {
        $proxima = obterProximaAbertura($config);
        return [
            'status_real' => false,
            'mensagem_real' => "Fechado aos Domingos • Abrimos {$proxima['dia_semana']} ({$proxima['data']}) às {$proxima['hora_abre']}",
            'proxima_abertura' => $proxima
        ];
    }
    
    // VERIFICAÇÃO 2: É feriado nacional?
    if (ehFeriado()) {
        $proxima = obterProximaAbertura($config);
        return [
            'status_real' => false,
            'mensagem_real' => "Fechado - Feriado Nacional • Abrimos {$proxima['dia_semana']} ({$proxima['data']}) às {$proxima['hora_abre']}",
            'proxima_abertura' => $proxima
        ];
    }
    
    // VERIFICAÇÃO 3: Verifica horário de funcionamento
    $horario_hoje = null;
    
    if ($diaDaSemana >= 1 && $diaDaSemana <= 5) { // Segunda a Sexta
        $horario_hoje = $config->horario_seg_sex;
    } elseif ($diaDaSemana == 6) { // Sábado
        $horario_hoje = $config->horario_sab;
    }

    // Se não há horário definido ou está explicitamente 'Fechado'
    if (!$horario_hoje || strtolower(trim($horario_hoje)) === 'fechado' || !strpos($horario_hoje, ' - ')) {
        $proxima = obterProximaAbertura($config);
        return [
            'status_real' => false,
            'mensagem_real' => "Fechado hoje • Abrimos {$proxima['dia_semana']} ({$proxima['data']}) às {$proxima['hora_abre']}",
            'proxima_abertura' => $proxima
        ];
    }

    // Extrai os horários de abertura e fechamento
    try {
        list($horaAbre, $horaFecha) = array_map('trim', explode(' - ', $horario_hoje));
        $horarioAbertura = DateTime::createFromFormat('H:i', $horaAbre);
        $horarioFechamento = DateTime::createFromFormat('H:i', $horaFecha);

        if (!$horarioAbertura || !$horarioFechamento) {
            throw new Exception('Formato de horário inválido');
        }

        // Verifica se o horário atual está dentro do intervalo de funcionamento
        if ($agora >= $horarioAbertura && $agora < $horarioFechamento) {
            $minutos_restantes = ($horarioFechamento->getTimestamp() - $agora->getTimestamp()) / 60;
            
            // Aviso quando estiver perto de fechar (últimos 30 minutos)
            if ($minutos_restantes <= 30) {
                return [
                    'status_real' => true,
                    'mensagem_real' => "Aberto • Fechamos às {$horaFecha} (em " . ceil($minutos_restantes) . " minutos)",
                    'proxima_abertura' => null
                ];
            }
            
            return [
                'status_real' => true,
                'mensagem_real' => "Aberto • Horário: {$horaAbre} às {$horaFecha}",
                'proxima_abertura' => null
            ];
        } else {
            // Fechado fora do horário
            $proxima = obterProximaAbertura($config);
            
            // Se vai abrir ainda hoje
            if ($agora < $horarioAbertura) {
                $minutos_ate_abrir = ($horarioAbertura->getTimestamp() - $agora->getTimestamp()) / 60;
                
                if ($minutos_ate_abrir <= 60) {
                    return [
                        'status_real' => false,
                        'mensagem_real' => "Abrimos em " . ceil($minutos_ate_abrir) . " minutos (às {$horaAbre})",
                        'proxima_abertura' => null
                    ];
                }
                
                return [
                    'status_real' => false,
                    'mensagem_real' => "Abrimos hoje às {$horaAbre}",
                    'proxima_abertura' => null
                ];
            }
            
            // Já passou o horário de funcionamento hoje
            return [
                'status_real' => false,
                'mensagem_real' => "Fechado • Abrimos {$proxima['dia_semana']} ({$proxima['data']}) às {$proxima['hora_abre']}",
                'proxima_abertura' => $proxima
            ];
        }
    } catch (Exception $e) {
        $proxima = obterProximaAbertura($config);
        return [
            'status_real' => false,
            'mensagem_real' => 'Fechado (horário não configurado)',
            'proxima_abertura' => $proxima
        ];
    }
}
?>