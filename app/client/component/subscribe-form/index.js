import domd from 'domd'

export default element => {
	const d = domd(element)
	const api_url = element.getAttribute('data-api-url')
	const buttonEl = element.querySelector('button')
	element.addEventListener('submit', async (ev) => {
		ev.preventDefault()
		buttonEl.disabled = true
		const email = element.querySelector('input[type="text"]').value
		const response = await fetch(`${api_url}?email=${encodeURIComponent(email)}`)

		if (response.status !== 200) {
			alert("An error occurred. Please try again later.")
			buttonEl.disabled = false
			return
		}

		const [err, result] = await response.json()
		if (err) {
			alert("You entered an incorrect email. Please double-check it.")
		} else {
			alert('We will notify you once it\'s ready. Thanks!')
		}

		buttonEl.disabled = false
	})

	return () => {}
}
