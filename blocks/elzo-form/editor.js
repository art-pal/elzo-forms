(function(blocks, blockEditor, components, element, serverSideRender) {
    'use strict';

    const config = window.ElzoFormsBlockEditor || {};
    const block = config.block || {};
    const strings = config.strings || {};
    const forms = Array.isArray(config.forms) ? config.forms : [];
    const createElement = element.createElement;
    const Fragment = element.Fragment;
    const InspectorControls = blockEditor.InspectorControls;
    const useBlockProps = blockEditor.useBlockProps;
    const FormControl = components.ComboboxControl || components.SelectControl;
    const Button = components.Button;
    const PanelBody = components.PanelBody;
    const Placeholder = components.Placeholder;
    const Spinner = components.Spinner;
    const Notice = components.Notice;
    const ServerSideRender = typeof serverSideRender === 'function'
        ? serverSideRender
        : serverSideRender && serverSideRender.ServerSideRender;
    const blockIcon = createElement(
        'svg',
        {
            width: 24,
            height: 24,
            viewBox: '0 0 24 24',
            fill: 'none',
            xmlns: 'http://www.w3.org/2000/svg',
            focusable: 'false',
            'aria-hidden': 'true',
        },
        createElement('rect', {
            width: 24,
            height: 24,
            fill: 'white',
        }),
        createElement('path', {
            d: 'M17 2.25C17.9665 2.25 18.75 3.0335 18.75 4V15.5684L17.25 17.0684V4C17.25 3.86193 17.1381 3.75 17 3.75H5C4.86193 3.75 4.75 3.86193 4.75 4V20C4.75 20.1381 4.86193 20.25 5 20.25H13.5684L15.0684 21.75H5C4.0335 21.75 3.25 20.9665 3.25 20V4C3.25 3.0335 4.0335 2.25 5 2.25H17ZM23.0303 15.5303L17 21.5605L13.4697 18.0303L14.5303 16.9697L17 19.4395L21.9697 14.4697L23.0303 15.5303ZM12 17.75H7V16.25H12V17.75ZM15 14.25H7V12.75H15V14.25ZM10 10.75H7V9.25H10V10.75ZM15 10.75H12V9.25H15V10.75ZM15 7.25H7V5.75H15V7.25Z',
            fill: 'black',
        })
    );

    function getSelectedForm(formId) {
        return forms.find(function(form) {
            return Number(form.id) === Number(formId);
        }) || null;
    }

    function getOptions() {
        return forms.map(function(form) {
            const status = form.status !== 'publish' && form.statusLabel
                ? ' — ' + form.statusLabel
                : '';

            return {
                value: String(form.id),
                label: form.title + status,
            };
        });
    }

    function FormSelector(props) {
        return createElement(
            'div',
            { className: 'elzo-forms-block-selector' },
            createElement(FormControl, {
                label: props.label,
                help: props.help,
                placeholder: strings.searchPlaceholder,
                value: props.formId > 0 ? String(props.formId) : null,
                options: getOptions(),
                onChange: function(value) {
                    const formId = value ? parseInt(value, 10) : 0;
                    props.onChange(Number.isInteger(formId) && formId > 0 ? formId : 0);
                },
            })
        );
    }

    function EmptyPreview() {
        return createElement(
            Notice,
            { status: 'warning', isDismissible: false },
            strings.previewEmpty
        );
    }

    function ErrorPreview() {
        return createElement(
            Notice,
            { status: 'error', isDismissible: false },
            strings.previewError
        );
    }

    function LoadingPreview() {
        return createElement(
            'div',
            { className: 'elzo-forms-block-preview-loading' },
            createElement(Spinner),
            createElement('span', null, strings.previewLoading)
        );
    }

    function SelectionPlaceholder(props) {
        const hasForms = forms.length > 0;
        const controls = [];
        const actions = [];

        if (hasForms) {
            controls.push(createElement(FormSelector, {
                key: 'selector',
                formId: props.formId,
                label: strings.selectForm,
                help: strings.selectFormHelp,
                onChange: props.onChange,
            }));
        }

        if (config.newFormUrl) {
            actions.push(createElement(
                Button,
                {
                    key: 'create',
                    variant: hasForms ? 'secondary' : 'primary',
                    href: config.newFormUrl,
                    target: '_blank',
                    rel: 'noopener noreferrer',
                },
                strings.createForm
            ));
        }

        if (actions.length > 0) {
            controls.push(createElement(
                'div',
                { key: 'actions', className: 'elzo-forms-block-placeholder-actions' },
                actions
            ));
        }

        return createElement(
            Placeholder,
            {
                icon: blockIcon,
                label: block.title || 'Elzo Form',
                instructions: hasForms ? strings.noFormSelected : strings.noForms,
            },
            controls
        );
    }

    const TextControl = components.TextControl;
    const SelectControl = components.SelectControl;
    const MAX_WIDTH_PATTERN = /^(\d+(\.\d+)?|\.\d+)(px|%|rem|em|vw|vh|vmin|vmax|ch|ex)?$/i;

    // Mirrors Form::sanitize_max_width(), which ignores any other value on render.
    function isValidMaxWidth(value) {
        const trimmed = String(value || '').trim();
        const match = trimmed.match(MAX_WIDTH_PATTERN);

        return trimmed === '' || (match !== null && parseFloat(match[1]) > 0);
    }

    function getAlignOptions() {
        return [
            { value: '', label: strings.alignDefault },
            { value: 'left', label: strings.alignLeft },
            { value: 'center', label: strings.alignCenter },
            { value: 'right', label: strings.alignRight },
        ];
    }

    function LayoutPanel(props) {
        const attributes = props.attributes;
        const maxWidth = attributes.maxWidth || '';

        return createElement(
            PanelBody,
            { title: strings.layout, initialOpen: false },
            createElement(TextControl, {
                label: strings.maxWidth,
                help: isValidMaxWidth(maxWidth) ? strings.maxWidthHelp : strings.maxWidthInvalid,
                value: maxWidth,
                onChange: function(value) {
                    props.setAttributes({ maxWidth: value });
                },
            }),
            createElement(SelectControl, {
                label: strings.formAlign,
                help: strings.formAlignHelp,
                value: attributes.formAlign || '',
                options: getAlignOptions(),
                onChange: function(value) {
                    props.setAttributes({ formAlign: value });
                },
            }),
            createElement(SelectControl, {
                label: strings.textAlign,
                value: attributes.textAlign || '',
                options: getAlignOptions(),
                onChange: function(value) {
                    props.setAttributes({ textAlign: value });
                },
            })
        );
    }

    blocks.registerBlockType(block.name || 'elzo-forms/form', {
        title: block.title || 'Elzo Form',
        description: block.description || '',
        category: block.category || 'widgets',
        icon: blockIcon,
        keywords: block.keywords || [],

        edit: function Edit(props) {
            const formId = Number(props.attributes.formId) || 0;
            const selectedForm = getSelectedForm(formId);
            const blockProps = useBlockProps({
                className: 'elzo-forms-block-editor',
            });
            const setFormId = function(value) {
                props.setAttributes({ formId: value });
            };
            let content;

            if (formId <= 0) {
                content = createElement(SelectionPlaceholder, {
                    formId: formId,
                    onChange: setFormId,
                });
            } else if (!selectedForm) {
                const unavailableControls = [];
                const unavailableActions = [];

                if (forms.length > 0) {
                    unavailableControls.push(createElement(FormSelector, {
                        key: 'selector',
                        formId: formId,
                        label: strings.selectForm,
                        help: strings.selectFormHelp,
                        onChange: setFormId,
                    }));
                }

                unavailableActions.push(createElement(
                    Button,
                    {
                        key: 'clear',
                        variant: 'secondary',
                        onClick: function() {
                            setFormId(0);
                        },
                    },
                    strings.clearSelection
                ));

                if (config.newFormUrl) {
                    unavailableActions.push(createElement(
                        Button,
                        {
                            key: 'create',
                            variant: 'secondary',
                            href: config.newFormUrl,
                            target: '_blank',
                            rel: 'noopener noreferrer',
                        },
                        strings.createForm
                    ));
                }

                unavailableControls.push(createElement(
                    'div',
                    { key: 'actions', className: 'elzo-forms-block-placeholder-actions' },
                    unavailableActions
                ));

                content = createElement(
                    Placeholder,
                    {
                        icon: blockIcon,
                        label: block.title || 'Elzo Form',
                        instructions: strings.unavailableForm,
                    },
                    unavailableControls
                );
            } else if (typeof ServerSideRender !== 'function') {
                content = createElement(ErrorPreview);
            } else {
                content = createElement(
                    'div',
                    {
                        className: 'elzo-forms-block-preview',
                        'aria-label': strings.previewLabel,
                        // Use an empty string so React versions bundled with
                        // supported WordPress releases emit the boolean HTML
                        // attribute without treating it as a React boolean prop.
                        inert: '',
                    },
                    createElement(ServerSideRender, {
                        block: block.name || 'elzo-forms/form',
                        attributes: props.attributes,
                        EmptyResponsePlaceholder: EmptyPreview,
                        ErrorResponsePlaceholder: ErrorPreview,
                        LoadingResponsePlaceholder: LoadingPreview,
                    })
                );
            }

            return createElement(
                Fragment,
                null,
                createElement(
                    InspectorControls,
                    null,
                    createElement(
                        PanelBody,
                        { title: strings.changeForm, initialOpen: true },
                        forms.length > 0
                            ? createElement(FormSelector, {
                                formId: formId,
                                label: strings.selectForm,
                                help: strings.selectFormHelp,
                                onChange: setFormId,
                            })
                            : createElement('p', null, strings.noForms)
                    ),
                    formId > 0
                        ? createElement(LayoutPanel, {
                            attributes: props.attributes,
                            setAttributes: props.setAttributes,
                        })
                        : null
                ),
                createElement('div', blockProps, content)
            );
        },

        save: function Save() {
            return null;
        },
    });
})(
    window.wp.blocks,
    window.wp.blockEditor,
    window.wp.components,
    window.wp.element,
    window.wp.serverSideRender
);
