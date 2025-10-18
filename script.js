// ARQUIVO: script.js (ATUALIZADO COM STORAGEMANAGER E CORREÇÃO DE ERRO)
document.addEventListener('DOMContentLoaded', () => {
    const productDisplayArea = document.getElementById('product-display-area');
    const searchInput = document.getElementById('searchInput');
    const promoFilter = document.getElementById('promoFilter');
    const sortOptions = document.getElementById('sortOptions');
    const sessionNav = document.getElementById('session-nav');
    const tagFilters = document.getElementById('tag-filters');

    // Elementos do Modal
    const modal = document.getElementById('product-details-modal');
    const modalCloseButton = document.getElementById('modal-close-button');
    const modalProductName = document.getElementById('modal-product-name');
    const modalProductImage = document.getElementById('modal-product-image');
    const modalProductPrice = document.getElementById('modal-product-price');
    const modalProductDescription = document.getElementById('modal-product-description');
    const modalAddToCartBtn = document.getElementById('modal-add-to-cart-btn');

    const API_PRODUTOS_URL = 'api/api_produtos.php';
    const API_FILTROS_URL = 'api/api_sessoes_tags.php';
    const SITE_INFO_API_URL = 'api/api_site_info.php';

    let queryParams = { q: '', promocao: false, ordenar: 'alfabetica_asc', tag: null };
    let siteConfig = null;
    let allProducts = [];

    // ✅ CORREÇÃO: Verifica se estamos na página de produtos antes de executar
    const isProductPage = productDisplayArea !== null;

    // --- LÓGICA DE RENDERIZAÇÃO E BUSCA ---
    const fetchAndRenderProducts = async () => {
        if (!productDisplayArea) return;
        productDisplayArea.innerHTML = '<div class="spinner"></div>';

        const url = new URL(API_PRODUTOS_URL, window.location.href);
        if (queryParams.q) url.searchParams.set('q', queryParams.q);
        if (queryParams.promocao) url.searchParams.set('promocao', 'true');
        if (queryParams.tag) url.searchParams.set('tag', queryParams.tag);
        url.searchParams.set('ordenar', queryParams.ordenar);

        try {
            const response = await fetch(url);
            if (!response.ok) throw new Error('Não foi possível carregar os produtos.');

            allProducts = await response.json();
            renderGroupedProducts(allProducts, siteConfig);
        } catch (error) {
            productDisplayArea.innerHTML = `<p class="error-message">${error.message}</p>`;
        }
    };

    const renderGroupedProducts = (products, config) => {
        productDisplayArea.innerHTML = '';
        if (products.length === 0) {
            productDisplayArea.innerHTML = `<p class="empty-message">Nenhum produto encontrado com os filtros atuais.</p>`;
            return;
        }

        const groupedBySession = products.reduce((acc, product) => {
            const sessionId = product.id_sessao;
            if (!acc[sessionId]) {
                acc[sessionId] = { name: product.sessao.nome, products: [] };
            }
            acc[sessionId].products.push(product);
            return acc;
        }, {});

        const podeEncomendar = config ? config.pode_encomendar : false;
        const mensagemFechado = config ? config.mensagem_loja_real : 'Indisponível';

        for (const sessionId in groupedBySession) {
            const group = groupedBySession[sessionId];
            const sessionHtml = `
                <div class="session-group" id="sessao-${sessionId}">
                    <h2 class="session-title">${group.name}</h2>
                    <div class="product-grid">
                        ${group.products.map(product => {
                            const formattedPrice = parseFloat(product.preco).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });
                            const priceLabel = product.unidade_medida === 'kg' ? `${formattedPrice} / kg` : formattedPrice;
                            const tagsHtml = product.tags.map(tag => `<span class="tag">${tag.nome}</span>`).join('');
                            return `
                                <div class="product-card" data-product-id="${product.id_produto}">
                                    ${product.em_promocao ? '<div class="promo-badge">OFERTA</div>' : ''}
                                    <img src="${product.imagem_url || 'https://placehold.co/400x400'}" alt="${product.nome}" class="product-image">
                                    <div class="product-info">
                                        <div class="product-tags">${tagsHtml}</div>
                                        <h3 class="product-name">${product.nome}</h3>
                                        <p class="product-price" data-price="${product.preco}">${priceLabel}</p>
                                        <button class="add-to-cart-btn ${!podeEncomendar ? 'disabled-btn' : ''}"
                                                data-id="${product.id_produto}" ${!podeEncomendar ? 'disabled' : ''}>
                                            ${podeEncomendar ? '<i class="fa-solid fa-plus"></i> Adicionar' : `<i class="fa-solid fa-ban"></i> ${mensagemFechado}`}
                                        </button>
                                    </div>
                                </div>
                            `;
                        }).join('')}
                    </div>
                </div>
            `;
            productDisplayArea.innerHTML += sessionHtml;
        }
    };

    // --- LÓGICA DO MODAL DE DETALHES ---
    const openProductModal = (productId) => {
        const product = allProducts.find(p => p.id_produto == productId);
        if (!product) return;

        const formattedPrice = parseFloat(product.preco).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });
        const priceLabel = product.unidade_medida === 'kg' ? `${formattedPrice} / kg` : formattedPrice;

        modalProductName.textContent = product.nome;
        modalProductImage.src = product.imagem_url || 'https://placehold.co/600x400';
        modalProductPrice.textContent = priceLabel;
        modalProductDescription.textContent = product.descricao || 'Este produto não possui uma descrição detalhada.';

        modalAddToCartBtn.dataset.id = product.id_produto;
        modalAddToCartBtn.dataset.name = product.nome;
        modalAddToCartBtn.dataset.price = product.preco;
        modalAddToCartBtn.dataset.image = product.imagem_url;
        modalAddToCartBtn.dataset.unit = product.unidade_medida === 'kg' ? 'kg' : 'un';

        modalAddToCartBtn.disabled = !(siteConfig && siteConfig.pode_encomendar);
        if (modalAddToCartBtn.disabled) {
            modalAddToCartBtn.innerHTML = `<i class="fa-solid fa-ban"></i> ${siteConfig.mensagem_loja_real}`;
            modalAddToCartBtn.classList.add('disabled-btn');
        } else {
             modalAddToCartBtn.innerHTML = `<i class="fa-solid fa-plus"></i> Adicionar Encomenda`;
             modalAddToCartBtn.classList.remove('disabled-btn');
        }

        modal.style.display = 'flex';
    };

    const closeProductModal = () => {
        modal.style.display = 'none';
    };

    // --- LÓGICA DE FILTROS E STATUS ---
    const fetchAndRenderFilters = async () => {
        // ✅ CORREÇÃO: Só executa se os elementos existirem
        if (!tagFilters || !sessionNav) {
            console.log('Elementos de filtro não encontrados. Pulando renderização de filtros.');
            return;
        }

        try {
            const response = await fetch(API_FILTROS_URL);
            
            if (!response.ok) {
                throw new Error(`Erro ${response.status}: Não foi possível carregar os filtros`);
            }
            
            const data = await response.json();
            
            if (!data || data.status === 'error') {
                throw new Error(data?.message || 'Dados de filtro inválidos');
            }

            const tags = data.tags || [];
            const sessoes = data.sessoes || [];

            // Renderiza filtros de tags
            tagFilters.innerHTML = '<button class="tag-filter-btn active" data-tag="all">Todas as Tags</button>';
            tags.forEach(tag => {
                tagFilters.innerHTML += `<button class="tag-filter-btn" data-tag="${tag.nome}">${tag.nome}</button>`;
            });

            // Renderiza navegação de sessões
            sessionNav.innerHTML = '<a href="#" class="session-nav-link active" data-id="all">Todas</a>';
            sessoes.forEach(sessao => {
                sessionNav.innerHTML += `<a href="#sessao-${sessao.id_sessao}" class="session-nav-link" data-id="${sessao.id_sessao}">${sessao.nome}</a>`;
            });

        } catch (error) {
            console.error("Erro ao buscar filtros:", error);
            
            if (tagFilters) {
                tagFilters.innerHTML = `<p class="error-message">Não foi possível carregar os filtros. ${error.message}</p>`;
            }
            if (sessionNav) {
                sessionNav.innerHTML = `<p class="error-message">Erro ao carregar sessões</p>`;
            }
        }
    };

    const fetchSiteInfo = async () => {
        try {
            const response = await fetch(`${SITE_INFO_API_URL}?_=${new Date().getTime()}`);
            if (!response.ok) throw new Error('Erro ao buscar informações do site');
            
            siteConfig = await response.json();
            const banner = document.getElementById('store-status-banner');
            
            if (banner) {
                banner.textContent = siteConfig.mensagem_loja_real || (siteConfig.pode_encomendar ? 'Loja aberta!' : 'Loja fechada.');
                banner.className = siteConfig.pode_encomendar ? 'status-aberto' : 'status-fechado';
                banner.style.display = 'block';
            }
        } catch (error) {
            console.error("Erro ao buscar informações do site:", error);
        }
    };

    // --- FUNÇÃO PARA VERIFICAÇÃO EM TEMPO REAL ---
    const checkStatusAndRefreshUI = async () => {
        if (!isProductPage) return; // Só verifica na página de produtos

        try {
            const response = await fetch(`${SITE_INFO_API_URL}?_=${new Date().getTime()}`);
            const newConfig = await response.json();
            
            if (siteConfig && newConfig.pode_encomendar !== siteConfig.pode_encomendar) {
                console.log('Status da loja alterado. Atualizando interface...');
                siteConfig = newConfig;
                
                const banner = document.getElementById('store-status-banner');
                if (banner) {
                    banner.textContent = siteConfig.mensagem_loja_real;
                    banner.className = siteConfig.pode_encomendar ? 'status-aberto' : 'status-fechado';
                }

                renderGroupedProducts(allProducts, siteConfig);
            }
        } catch (error) {
            console.error('Erro ao verificar status da loja:', error);
        }
    };

    // --- EVENT LISTENERS ---
    if (productDisplayArea) {
        productDisplayArea.addEventListener('click', (e) => {
            const addToCartBtn = e.target.closest('.add-to-cart-btn');
            const card = e.target.closest('.product-card');

            if (addToCartBtn && !addToCartBtn.disabled) {
                e.stopPropagation();
                const productData = {
                    id: addToCartBtn.dataset.id,
                    name: card.querySelector('.product-name').textContent,
                    price: card.querySelector('.product-price').dataset.price,
                    image: card.querySelector('.product-image').src,
                    unit: card.querySelector('.product-price').textContent.includes('/ kg') ? 'kg' : 'un',
                };
                addToCart(productData);
            } else if (card) {
                openProductModal(card.dataset.productId);
            }
        });
    }

    if (modalAddToCartBtn) {
        modalAddToCartBtn.addEventListener('click', () => {
            const productData = {
                id: modalAddToCartBtn.dataset.id,
                name: modalAddToCartBtn.dataset.name,
                price: modalAddToCartBtn.dataset.price,
                image: modalAddToCartBtn.dataset.image,
                unit: modalAddToCartBtn.dataset.unit,
            };
            addToCart(productData);
            closeProductModal();
        });
    }

    if (modalCloseButton) {
        modalCloseButton.addEventListener('click', closeProductModal);
    }

    if (modal) {
        modal.addEventListener('click', (e) => {
            if (e.target === modal) closeProductModal();
        });
    }

    if (searchInput) {
        searchInput.addEventListener('input', () => { 
            queryParams.q = searchInput.value; 
            fetchAndRenderProducts(); 
        });
    }

    if (promoFilter) {
        promoFilter.addEventListener('change', () => { 
            queryParams.promocao = promoFilter.checked; 
            fetchAndRenderProducts(); 
        });
    }

    if (sortOptions) {
        sortOptions.addEventListener('change', () => { 
            queryParams.ordenar = sortOptions.value; 
            fetchAndRenderProducts(); 
        });
    }

    if (tagFilters) {
        tagFilters.addEventListener('click', (e) => {
            const btn = e.target.closest('.tag-filter-btn');
            if (btn) {
                const activeBtn = tagFilters.querySelector('.active');
                if (activeBtn) activeBtn.classList.remove('active');
                btn.classList.add('active');
                queryParams.tag = btn.dataset.tag === 'all' ? null : btn.dataset.tag;
                fetchAndRenderProducts();
            }
        });
    }

    if (sessionNav) {
        sessionNav.addEventListener('click', (e) => {
            const link = e.target.closest('.session-nav-link');
            if (link) {
                e.preventDefault();
                const targetId = link.getAttribute('href');
                if (targetId === '#') {
                     window.scrollTo({ top: 0, behavior: 'smooth' });
                } else {
                    const targetElement = document.querySelector(targetId);
                    if (targetElement) {
                        targetElement.scrollIntoView({ behavior: 'smooth' });
                    }
                }
                const activeLink = sessionNav.querySelector('.active');
                if (activeLink) activeLink.classList.remove('active');
                link.classList.add('active');
            }
        });
    }

    // --- INICIALIZAÇÃO ---
    const initPage = async () => {
        await fetchSiteInfo();
        
        // ✅ CORREÇÃO: Só busca filtros e produtos na página de produtos
        if (isProductPage) {
            await fetchAndRenderFilters();
            await fetchAndRenderProducts();
            
            // Inicia a verificação em tempo real apenas na página de produtos
            setInterval(checkStatusAndRefreshUI, 30000);
        }
        
        updateCartCounter();
    };

    initPage();
});

// Funções globais do carrinho (ATUALIZADAS COM STORAGEMANAGER)
function addToCart(product) {
    let cart = StorageManager.getCarrinho();
    const existingItem = cart.find(item => item.id == product.id);
    if (existingItem) {
        existingItem.quantity = product.unit === 'kg' ? (parseFloat(existingItem.quantity) + 0.1).toFixed(2) : existingItem.quantity + 1;
    } else {
        product.quantity = product.unit === 'kg' ? 0.1 : 1;
        cart.push(product);
    }
    StorageManager.setCarrinho(cart);
    updateCartCounter();
    showAddedToCartFeedback(product.id);
}

function showAddedToCartFeedback(productId) {
    const btn = document.querySelector(`.add-to-cart-btn[data-id='${productId}']`);
    if (btn) {
        btn.innerHTML = `<i class="fa-solid fa-check"></i> Adicionado!`;
        btn.style.backgroundColor = 'var(--success-green)';
        setTimeout(() => {
            btn.innerHTML = `<i class="fa-solid fa-plus"></i> Adicionar`;
            btn.style.backgroundColor = '';
        }, 1500);
    }
}

function updateCartCounter() {
    const cart = StorageManager.getCarrinho();
    const totalItems = cart.length;
    const cartCounter = document.getElementById('cart-counter');
    if (cartCounter) {
        cartCounter.textContent = totalItems;
        cartCounter.style.display = totalItems > 0 ? 'flex' : 'none';
    }
}

window.updateCartCounter = updateCartCounter;