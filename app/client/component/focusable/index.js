import domd from 'domd'

export default element => {
	const d = domd(element)
	const textEl = element.querySelector('input[type="text"]')
	d.on('change', 'input[id^="toggle-"]', (ev, el) => {
		d.on('transitionend', 'form', (ev, el) => {
			textEl.focus()
			d.off('transitionend', 'form')
		})
	})

	return () => {}
}
