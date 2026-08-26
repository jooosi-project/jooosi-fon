function jooosi_fon_add_fonts(options) {
	const { __ } = wp.i18n;
	const jooosi_fonts = [
		{
			type: 'group',
			label: __('Jooosi Fon', 'jooosi-fon'),
			options: jooosiFonKadenceBlocks.fonts,
		},
	];

	options = jooosi_fonts.concat(options);

	return options;
}
wp.hooks.addFilter('kadence.typography_options', 'jooosi/fon/add_fonts', jooosi_fon_add_fonts);
