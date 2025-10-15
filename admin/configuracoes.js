document.addEventListener('DOMContentLoaded', () => {
    const API_URL = '../api/api_admin_configuracoes.php';
    const form = document.getElementById('settingsForm');

    const fetchSettings = async () => {
        try {
            const response = await fetch(API_URL);
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            const settings = await response.json();
            
            if (settings) {
                if (settings.horario_seg_sex && settings.horario_seg_sex.includes(' - ')) {
                    const [abre, fecha] = settings.horario_seg_sex.split(' - ');
                    document.getElementById('horario_seg_sex_abre').value = abre || '';
                    document.getElementById('horario_seg_sex_fecha').value = fecha || '';
                }

                if (settings.horario_sab && settings.horario_sab.includes(' - ')) {
                    const [abre, fecha] = settings.horario_sab.split(' - ');
                    document.getElementById('horario_sab_abre').value = abre || '';
                    document.getElementById('horario_sab_fecha').value = fecha || '';
                }

                // CORREÇÃO: Lendo da coluna 'status_manual'
                document.getElementById('status_manual').value = settings.status_manual || 'auto';
                document.getElementById('encomendas_ativas').checked = settings.encomendas_ativas;
                // CORREÇÃO: Lendo da coluna 'mensagem_status'
                document.getElementById('mensagem_status').value = settings.mensagem_status || '';
            }
        } catch (error) {
            console.error("Erro detalhado:", error);
            alert('Erro ao carregar as configurações. Verifique o console para mais detalhes.');
        }
    };

    form.addEventListener('submit', async (e) => {
        e.preventDefault();
        const formData = new FormData(form);

        const data = {
            horario_seg_sex: `${formData.get('horario_seg_sex_abre')} - ${formData.get('horario_seg_sex_fecha')}`,
            horario_sab: `${formData.get('horario_sab_abre')} - ${formData.get('horario_sab_fecha')}`,
            // CORREÇÃO: Enviando para a coluna 'status_manual'
            status_manual: formData.get('status_manual'),
            encomendas_ativas: document.getElementById('encomendas_ativas').checked,
            // CORREÇÃO: Enviando para a coluna 'mensagem_status'
            mensagem_status: formData.get('mensagem_status')
        };

        try {
            const response = await fetch(API_URL, {
                method: 'PATCH',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(data)
            });

            const result = await response.json();
            if (response.ok) {
                 alert(result.message || 'Configurações salvas com sucesso!');
            } else {
                const errorMessage = result.message + (result.response_body ? ` (Detalhe: ${result.response_body})` : '');
                throw new Error(errorMessage || 'Erro desconhecido.');
            }
        } catch (error) {
            alert('Erro ao salvar as configurações: ' + error.message);
        }
    });

    fetchSettings();
});