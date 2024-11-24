@php($placeholders = app('\Webkul\Automation\Helpers\Entity')->getEmailTemplatePlaceholders())

<v-tinymce {{ $attributes }}></v-tinymce>

@pushOnce('scripts')
    <!--
        TODO (@devansh-webkul): Only this portion is pending; it just needs to be integrated using the Vite bundler. Currently,
        there is an issue with relative paths in the plugins. I intend to address this task at the end.
    -->
    <script
        src="https://cdnjs.cloudflare.com/ajax/libs/tinymce/6.6.2/tinymce.min.js"
        crossorigin="anonymous"
        referrerpolicy="no-referrer"
    ></script>

    <script
        type="text/x-template"
        id="v-tinymce-template"
    >
    </script>

    <script type="module">
        app.component('v-tinymce', {
            template: '#v-tinymce-template',
                
            props: ['selector', 'field'],

            data() {
                return {
                    currentSkin: document.documentElement.classList.contains('dark') ? 'oxide-dark' : 'oxide',

                    currentContentCSS: document.documentElement.classList.contains('dark') ? 'dark' : 'default',

                    isLoading: false,
                };
            },

            mounted() {
                tinymce.remove(this.selector);

                this.init();

                this.$emitter.on('change-theme', (theme) => {
                    tinymce.activeEditor.destroy();

                    this.currentSkin = (theme === 'dark') ? 'oxide-dark' : 'oxide';
                    this.currentContentCSS = (theme === 'dark') ? 'dark' : 'default';

                    this.init();
                });
            },

            methods: {
                init() {
                    let self = this;

                    let tinyMCEHelper = {
                        initTinyMCE: function(extraConfiguration) {
                            let self2 = this;

                            let config = {  
                                relative_urls: false,
                                menubar: false,
                                remove_script_host: false,
                                document_base_url: '{{ asset('/') }}',
                                uploadRoute: '{{ route('admin.tinymce.upload') }}',
                                csrfToken: '{{ csrf_token() }}',
                                ...extraConfiguration,
                                skin: self.currentSkin,
                                content_css: self.currentContentCSS,
                            };

                            const image_upload_handler = (blobInfo, progress) => new Promise((resolve, reject) => {
                                self2.uploadImageHandler(config, blobInfo, resolve, reject, progress);
                            });

                            tinymce.init({
                                ...config,

                                file_picker_callback: function(cb, value, meta) {
                                    self2.filePickerCallback(config, cb, value, meta);
                                },

                                images_upload_handler: image_upload_handler,
                            });
                        },

                        filePickerCallback: function(config, cb, value, meta) {
                            let input = document.createElement('input');
                            input.setAttribute('type', 'file');
                            input.setAttribute('accept', 'image/*');

                            input.onchange = function() {
                                let file = this.files[0];

                                let reader = new FileReader();
                                reader.readAsDataURL(file);
                                reader.onload = function() {
                                    let id = 'blobid' + new Date().getTime();
                                    let blobCache = tinymce.activeEditor.editorUpload.blobCache;
                                    let base64 = reader.result.split(',')[1];
                                    let blobInfo = blobCache.create(id, file, base64);

                                    blobCache.add(blobInfo);

                                    cb(blobInfo.blobUri(), {
                                        title: file.name
                                    });
                                };
                            };

                            input.click();
                        },

                        uploadImageHandler: function(config, blobInfo, resolve, reject, progress) {
                            let xhr, formData;

                            xhr = new XMLHttpRequest();

                            xhr.withCredentials = false;

                            xhr.open('POST', config.uploadRoute);

                            xhr.upload.onprogress = ((e) => progress((e.loaded / e.total) * 100));

                            xhr.onload = function() {
                                let json;

                                if (xhr.status === 403) {
                                    reject("@lang('admin::app.error.tinymce.http-error')", {
                                        remove: true
                                    });

                                    return;
                                }

                                if (xhr.status < 200 || xhr.status >= 300) {
                                    reject("@lang('admin::app.error.tinymce.http-error')");

                                    return;
                                }

                                json = JSON.parse(xhr.responseText);

                                if (! json || typeof json.location != 'string') {
                                    reject("@lang('admin::app.error.tinymce.invalid-json')" + xhr.responseText);

                                    return;
                                }

                                resolve(json.location);
                            };

                            xhr.onerror = (()=>reject("@lang('admin::app.error.tinymce.upload-failed')"));

                            formData = new FormData();
                            formData.append('_token', config.csrfToken);
                            formData.append('file', blobInfo.blob(), blobInfo.filename());

                            xhr.send(formData);
                        },
                    };

                    tinyMCEHelper.initTinyMCE({
                        selector: this.selector,
                        plugins: 'image media wordcount save fullscreen code table lists link',
                        toolbar: 'placeholders | bold italic strikethrough forecolor backcolor image alignleft aligncenter alignright alignjustify | link hr | numlist bullist outdent indent | removeformat | code | table',
                        image_advtab: true,
                        directionality: 'ltr',
                        setup: (editor) => {
                            let toggleState = false;

                            editor.ui.registry.addMenuButton('placeholders', {
                                text: 'Placeholders',
                                fetch: function (callback) {
                                    const items = [
                                        @foreach($placeholders as $placeholder)
                                            {
                                                type: 'nestedmenuitem',
                                                text: '{{ $placeholder['text'] }}',
                                                getSubmenuItems: () => [
                                                    @foreach($placeholder['menu'] as $child)
                                                        {
                                                            type: 'menuitem',
                                                            text: '{{ $child['text'] }}',
                                                            onAction: function () {
                                                                editor.insertContent('{{ $child['value'] }}');
                                                            },
                                                        },
                                                    @endforeach
                                                ],
                                            },
                                        @endforeach
                                    ];

                                    callback(items);
                                }
                            });

                            editor.on('keyup', () => this.field.onInput(editor.getContent()));
                        }
                    });
                },
            },
        })
    </script>
@endPushOnce