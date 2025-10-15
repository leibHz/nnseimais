document.addEventListener('DOMContentLoaded', () => {
    const API_URL = '../api/api_admin_produtos.php';
    const tableBody = document.getElementById('productsTableBody');
    const modal = document.getElementById('productModal');
    const modalTitle = document.getElementById('modalTitle');
    const productForm = document.getElementById('productForm');
    const addProductBtn = document.getElementById('addProductBtn');
    const closeBtns = document.querySelectorAll('.close-btn');
    const sessaoSelect = document.getElementById('id_sessao');
    const imagePreviewContainer = document.getElementById('imagePreviewContainer');
    const imagePreview = document.getElementById('imagePreview');
    const tagsCheckboxContainer = document.getElementById('tags-checkbox-container');

    const fetchProducts = async () => {
        try {
            tableBody.innerHTML = `<tr><td colspan="6">A carregar produtos...</td></tr>`;
            const response = await fetch(API_URL);
            if (!response.ok) throw new Error('Falha ao carregar produtos.');
            const products = await response.json();
            renderProducts(products);
        } catch (error) {
            tableBody.innerHTML = `<tr><td colspan="6">${error.message}</td></tr>`;
        }
    };

    const fetchSessoesAndTags = async () => {
        try {
            const [sessoesRes, tagsRes] = await Promise.all([
                fetch(`${API_URL}?sessoes=true`),
                fetch(`${API_URL}?tags=true`)
            ]);
            const sessoes = await sessoesRes.json();
            const tags = await tagsRes.json();

            sessaoSelect.innerHTML = '<option value="">Selecione uma sessão</option>';
            sessoes.forEach(sessao => {
                sessaoSelect.innerHTML += `<option value="${sessao.id_sessao}">${sessao.nome}</option>`;
            });

            tagsCheckboxContainer.innerHTML = '';
            if (tags.length === 0) {
                tagsCheckboxContainer.innerHTML = '<p>Nenhuma tag cadastrada.</p>';
            } else {
                tags.forEach(tag => {
                    const itemDiv = document.createElement('div');
                    itemDiv.className = 'checkbox-item';
                    const checkbox = document.createElement('input');
                    checkbox.type = 'checkbox';
                    checkbox.id = `tag-${tag.id_tag}`;
                    checkbox.name = 'tags[]';
                    checkbox.value = tag.id_tag;
                    const label = document.createElement('label');
                    label.htmlFor = `tag-${tag.id_tag}`;
                    label.textContent = tag.nome;
                    itemDiv.appendChild(checkbox);
                    itemDiv.appendChild(label);
                    tagsCheckboxContainer.appendChild(itemDiv);
                });
            }
        } catch (error) {
            console.error("Erro ao carregar sessões e tags:", error);
        }
    };

    const renderProducts = (products) => {
        tableBody.innerHTML = '';
        if (!products || products.length === 0) {
            tableBody.innerHTML = '<tr><td colspan="6">Nenhum produto cadastrado.</td></tr>';
            return;
        }
        products.forEach(p => {
            const row = `
                <tr>
                    <td><span class="status ${p.disponivel ? 'status-disponivel' : 'status-indisponivel'}">${p.disponivel ? 'Sim' : 'Não'}</span></td>
                    <td>${p.nome} ${p.em_promocao ? '<span class="promo-tag-list">Promo</span>' : ''}</td>
                    <td>R$ ${parseFloat(p.preco).toFixed(2)}</td>
                    <td>${p.codigo_barras || 'N/A'}</td>
                    <td>${p.sessao?.nome || 'N/A'}</td>
                    <td class="actions-cell">
                        <button class="edit-btn" data-id="${p.id_produto}"><i class="fa-solid fa-pencil"></i></button>
                        <button class="delete-btn" data-id="${p.id_produto}"><i class="fa-solid fa-trash-can"></i></button>
                    </td>
                </tr>`;
            tableBody.insertAdjacentHTML('beforeend', row);
        });
    };

    const openModal = (product = null) => {
        productForm.reset();
        imagePreviewContainer.style.display = 'none';
        document.getElementById('disponivel').checked = true;
        document.getElementById('em_promocao').checked = false;
        tagsCheckboxContainer.querySelectorAll('input').forEach(cb => cb.checked = false);

        if (product) {
            modalTitle.textContent = 'Editar Produto';
            document.getElementById('productId').value = product.id_produto;
            document.getElementById('nome').value = product.nome;
            document.getElementById('preco').value = product.preco;
            document.getElementById('codigo_barras').value = product.codigo_barras || '';
            document.getElementById('id_sessao').value = product.id_sessao;
            document.getElementById('descricao').value = product.descricao || '';
            document.getElementById('unidade_medida').value = product.unidade_medida;
            document.getElementById('disponivel').checked = product.disponivel;
            document.getElementById('em_promocao').checked = product.em_promocao;
            
            // *** CORREÇÃO APLICADA AQUI ***
            // Garante que 'product.tags' seja um array antes de tentar iterar sobre ele.
            let tagsParaProcessar = product.tags;
            if (typeof tagsParaProcessar === 'string') {
                try {
                    tagsParaProcessar = JSON.parse(tagsParaProcessar);
                } catch (e) {
                    console.error("Erro ao parsear as tags do produto:", e);
                    tagsParaProcessar = []; // Define como array vazio em caso de erro
                }
            }

            if (Array.isArray(tagsParaProcessar)) {
                tagsParaProcessar.forEach(tag => {
                    const checkbox = document.getElementById(`tag-${tag.id_tag}`);
                    if (checkbox) checkbox.checked = true;
                });
            }

            if (product.imagem_url) {
                document.getElementById('imagem_atual').value = product.imagem_url;
                imagePreview.src = `../${product.imagem_url}`;
                imagePreviewContainer.style.display = 'block';
            }
        } else {
            modalTitle.textContent = 'Adicionar Novo Produto';
        }
        modal.style.display = 'block';
    };

    const closeModal = () => modal.style.display = 'none';

    addProductBtn.addEventListener('click', () => openModal());
    closeBtns.forEach(btn => btn.addEventListener('click', closeModal));
    window.addEventListener('click', e => e.target == modal && closeModal());

    productForm.addEventListener('submit', async (e) => {
        e.preventDefault();

        const selectedTags = productForm.querySelectorAll('input[name="tags[]"]:checked');
        if (selectedTags.length === 0) {
            alert('Por favor, selecione pelo menos uma tag para o produto.');
            return;
        }
        if (!document.getElementById('codigo_barras').value.trim()) {
            alert('O código de barras é obrigatório.');
            return;
        }

        const formData = new FormData(productForm);
        try {
            const response = await fetch(API_URL, {
                method: 'POST',
                body: formData
            });
            const resultText = await response.text();
            const result = JSON.parse(resultText);

            if (result.status === 'success') {
                closeModal();
                fetchProducts();
            } else {
                throw new Error(result.message);
            }
        } catch (error) {
            alert('Erro ao salvar: ' + error.message);
        }
    });

    tableBody.addEventListener('click', async (e) => {
        const targetButton = e.target.closest('button');
        if (!targetButton) return;
        const id = targetButton.dataset.id;

        if (targetButton.classList.contains('edit-btn')) {
            const response = await fetch(`${API_URL}?id=${id}`);
            const product = await response.json();
            openModal(product || null);
        }

        if (targetButton.classList.contains('delete-btn')) {
            if (confirm('Tem certeza que deseja remover este produto?')) {
                const response = await fetch(API_URL, {
                    method: 'DELETE',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ id_produto: id })
                });
                const result = await response.json();
                if (result.status === 'success') fetchProducts();
                else alert(result.message);
            }
        }
    });
    
    fetchProducts();
    fetchSessoesAndTags();
});

