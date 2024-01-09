import domd from 'domd'

export default element => {
	const d = domd(element)
	const api_url = element.getAttribute('data-api-url')

	d.on('click', 'button', async (ev, el) => {
		el.disabled = true
		const email = element.querySelector('input[type="text"]').value
	  const response = await fetch(`${api_url}?email=${encodeURIComponent(email)}`)
	  const [err, result] = await response.json()
	  alert('We will notify once it\s ready. Thanks!')
	  el.disabled = false
	})

	return () => {}
}
