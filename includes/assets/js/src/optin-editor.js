import noptinSelectComponent from './noptin-select.vue'
import swatches from 'vue-swatches'
import popover from 'vue-popperjs'
import noptinMediumEditor from 'medium-editor'
import draggable from 'vuedraggable'
import fieldEditor from './field-editor.js'
import noptinForm from './noptin-form.js'
import noptin from './noptin.js'
import noptinEditorComponent from './css-editor.vue'

var vm = new Vue({

	components: {

		//Select2
		'noptin-select': noptinSelectComponent,

		//Drag drop
		draggable,

		 //Color swatches
		'noptin-swatch': swatches,

		 //Tooltips
		'noptin-tooltip': popover,

		//Optin fields editor
		'field-editor': fieldEditor,

		//Renders the optin forms
		'noptinform': noptinForm,

		//Custom CSS Editor
		'noptineditor': noptinEditorComponent,

		//WYIWYG
		'noptin-rich-text': noptinMediumEditor,

	},

	el: '#noptin-form-editor',

	data: jQuery.extend(true, {}, noptinEditor.data),

	computed: {
		_onlyShowOn () {
			return this.onlyShowOn && this.onlyShowOn.length > 0
		}
	},

	methods: {

		togglePanel (id) {

			var el = $('#' + id)
			var isOpen = $(el).hasClass('open')

			//toggle arrows
			$(el).find('> .noptin-popup-editor-panel-header .dashicons-arrow-up-alt2').slideToggle()
			$(el).find('> .noptin-popup-editor-panel-header .dashicons-arrow-down-alt2').slideToggle()

			//toggle the body with a sliding motion
			$(el).find('> .noptin-popup-editor-panel-body').slideToggle()

			//Toggle the open class
			$(el).toggleClass('open')


		},

		previewPopup () {
			this.isPreviewShowing = true
			var _html = jQuery('.noptin-popup-wrapper').html()
			jQuery("#noptin-popup-preview")
				.html(_html)
				.addClass('noptin-preview-showing')
				.find('.noptin-popup-close')
				.show()

			//Hide popup when user clicks outside
			jQuery("#noptin-popup-preview")
				.off('noptin-popup')
				.on('click', (e) => {
					var container = jQuery(this).find(".noptin-popup-form-wrapper");

					// if the target of the click isn't the container nor a descendant of the container
					if (!container.is(e.target) && container.has(e.target).length === 0) {
						vm.closePopup()
					}
				});
		},
		closePopup () {
			this.isPreviewShowing = false
			jQuery("#noptin-popup-preview").removeClass('noptin-preview-showing').html('')
		},
		saveAsTemplate () {
			var saveText = this.saveAsTemplateText
			this.saveAsTemplateText = this.savingTemplateText;
			var that = this

			jQuery.post(noptinEditor.ajaxurl, {
				_ajax_nonce: noptinEditor.nonce,
				action: "noptin_save_optin_form_as_template",
				state: vm.$data
			})
				.done( () => {
					that.showSuccess(that.savingTemplateSuccess)
					that.saveAsTemplateText = saveText
				})
				.fail( () => {
					that.showError(that.savingTemplateError)
					that.saveAsTemplateText = saveText
				})

		},

		upload_image (key, size) {

			if ('undefined' == typeof size) {
				size = 'thumbnail'
			}

			var image = wp.media({
				title: 'Upload Image',
				multiple: false
			})
				.open()
				.on('select', (e) => {
					var uploaded_image = image.state().get('selection').first();
					vm[key] = uploaded_image.toJSON().sizes[size].url;
				})
		},
		showSuccess (msg) {
			this.hasSuccess = true;
			this.Success = msg;

			setTimeout( () => {
				vm.hasSuccess = false;
				vm.Success = '';
			}, 5000)
		},
		showError (msg) {
			this.hasError = true;
			this.Error = msg;

			setTimeout( () => {
				vm.hasError = false;
				vm.Error = '';
			}, 5000)
		},
		publish () {
			this.optinStatus = true
		},
		unpublish () {
			this.optinStatus = false
		},
		copyShortcode (e) {
			var text = "[noptin-form id=" + this.id + "]"
			this.copy(text, e)
		},
		copy (text, e) {

			var textarea =
				$('<textarea>')
					.css({
						position: 'fixed',
						top: 0,
						left: 0,
						width: '2em',
						height: '2em',
						padding: '2em',
						border: 'none',
						outline: 'none',
						boxShadow: 'none',
					})
					.val(text)
					.appendTo('body')
					.focus()
					.select()
			var el = $(e.target).parent().find('.noptin-copy-button')

			try {
				var successful = document.execCommand('copy');
				var msg = successful ? 'copied' : 'error';

				el.text(msg).addClass('copied')
			} catch (err) {
				el.text('error').addClass('copied')
			}

			setTimeout( () => {
				el.text('Copied').removeClass('copied')
			}, 400)

			textarea.remove()
		},
		save () {
			var saveText = this.saveText
			this.saveText = this.savingText;
			var that = this

			jQuery.post(noptinEditor.ajaxurl, {
				_ajax_nonce: noptinEditor.nonce,
				action: "noptin_save_optin_form",
				state: vm.$data,
				html: jQuery('.noptin-popup-wrapper').html()
			})
				.done( () => {
					that.showSuccess(that.savingSuccess)
					that.saveText = saveText
				})
				.fail( () => {
					that.showError(that.savingError)
					that.saveText = saveText
				})

		}
	},

	watch: {
		Template () {
			var template = noptin.templateData(this.Template)
			noptin.applyTemplate(template, this)
		},
		optinStatus () {
			this.save()
		},
		CSS () {
			noptin.updateCustomCss(this.CSS)
		},
		optinType () {
			noptin.updateFormSizes(this)
		},
		colorTheme () {
			noptin.changeColorTheme(this)
		}
	},

	mounted () {
		noptin.updateCustomCss(this.CSS)
		jQuery('.noptin-form-designer-loader').hide()
		jQuery(this.$el).find('.noptin-popup-editor-main-preview-name-textarea').focus()
	},

})

export default vm