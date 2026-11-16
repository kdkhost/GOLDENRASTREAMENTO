{{-- Modal para Download do Aplicativo Slack pelo Administrador --}}
<div class="modal fade" id="modalSlackDownload" tabindex="-1" aria-labelledby="modalSlackDownloadLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content shadow-lg border-0">
            <div class="modal-header border-bottom py-3">
                <div class="d-flex align-items-center gap-2">
                    <span class="badge rounded-circle p-2 bg-dark text-warning fs-5">
                        <i class="fab fa-slack" aria-hidden="true"></i>
                    </span>
                    <div>
                        <h5 class="modal-title fw-bold mb-0" id="modalSlackDownloadLabel">Baixar Aplicativo Slack</h5>
                        <small class="text-secondary">Central de alertas, comunicação corporativa e acompanhamento em tempo real</small>
                    </div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
            </div>
            <div class="modal-body p-4">
                <div class="row g-4 align-items-stretch">
                    {{-- Computadores (Windows e Mac) --}}
                    <div class="col-md-6">
                        <div class="card h-100 border bg-body-tertiary">
                            <div class="card-body d-flex flex-column">
                                <div class="d-flex align-items-center gap-2 mb-3">
                                    <i class="fas fa-desktop text-golden fs-4"></i>
                                    <div>
                                        <h6 class="fw-bold mb-0">Computador / Desktop</h6>
                                        <small class="text-secondary">Instale no Windows ou Mac</small>
                                    </div>
                                </div>
                                <p class="small text-secondary mb-3">
                                    Receba notificações nativas na bandeja do sistema, pop-ups de novos leads e pedidos, e mantenha a equipe conectada.
                                </p>
                                <div class="d-grid gap-2 mt-auto">
                                    <a href="https://slack.com/ssb/download-win64" target="_blank" rel="noopener" class="btn btn-golden d-flex align-items-center justify-content-center gap-2">
                                        <i class="fab fa-windows fs-5"></i>
                                        <span>Baixar para <strong>Windows (64-bit)</strong></span>
                                    </a>
                                    <a href="https://slack.com/ssb/download-mac" target="_blank" rel="noopener" class="btn btn-outline-secondary d-flex align-items-center justify-content-center gap-2">
                                        <i class="fab fa-apple fs-5"></i>
                                        <span>Baixar para <strong>macOS (Apple)</strong></span>
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- Celular & Dispositivos Móveis --}}
                    <div class="col-md-6">
                        <div class="card h-100 border bg-body-tertiary">
                            <div class="card-body d-flex flex-column">
                                <div class="d-flex align-items-center gap-2 mb-3">
                                    <i class="fas fa-mobile-screen text-golden fs-4"></i>
                                    <div>
                                        <h6 class="fw-bold mb-0">Smartphone &amp; Tablet</h6>
                                        <small class="text-secondary">Alertas sonoros no celular</small>
                                    </div>
                                </div>
                                <p class="small text-secondary mb-3">
                                    Notificações push em tempo real com som na tela de bloqueio do seu smartphone em qualquer lugar.
                                </p>
                                <div class="d-grid gap-2 mt-auto">
                                    <a href="https://play.google.com/store/apps/details?id=com.Slack" target="_blank" rel="noopener" class="btn btn-outline-dark d-flex align-items-center justify-content-center gap-2">
                                        <i class="fab fa-google-play fs-5 text-success"></i>
                                        <span>Instalar no <strong>Android (Google Play)</strong></span>
                                    </a>
                                    <a href="https://apps.apple.com/app/slack/id618783545" target="_blank" rel="noopener" class="btn btn-outline-dark d-flex align-items-center justify-content-center gap-2">
                                        <i class="fab fa-app-store-ios fs-5 text-primary"></i>
                                        <span>Instalar no <strong>iPhone / iOS (App Store)</strong></span>
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- Web App & Instruções Rápidas --}}
                    <div class="col-12">
                        <div class="p-3 border rounded bg-body d-flex flex-wrap align-items-center justify-content-between gap-3">
                            <div>
                                <span class="fw-semibold d-block"><i class="fas fa-globe text-golden me-1"></i> Acessar Slack direto no Navegador Web</span>
                                <small class="text-secondary">Não quer instalar nada agora? Use o Slack Web direto pela aba do seu navegador.</small>
                            </div>
                            <a href="https://slack.com/signin" target="_blank" rel="noopener" class="btn btn-sm btn-outline-golden">
                                <i class="fas fa-arrow-up-right-from-square me-1"></i> Abrir Slack Web
                            </a>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer bg-body-tertiary py-2 px-4 justify-content-between">
                <small class="text-secondary">
                    <i class="fas fa-shield-alt text-golden me-1"></i> Integração oficial Golden Rastreamento via Incoming Webhooks.
                </small>
                <div class="d-flex gap-2">
                    <a href="{{ route('admin.notificacoes.preferencias') }}" class="btn btn-sm btn-outline-secondary">
                        <i class="fas fa-sliders me-1"></i> Configurar Webhook do Slack
                    </a>
                    <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Fechar</button>
                </div>
            </div>
        </div>
    </div>
</div>
