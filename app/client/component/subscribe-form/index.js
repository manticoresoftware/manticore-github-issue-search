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
	  const [err, result] = await response.json()
	  if (err) {
	  	alert("You entered wrong emeail. Please, double-check it.")
	  } else {
	  	alert('We will notify once it\'s ready. Thanks!')
	  }

	  buttonEl.disabled = false
	})

	return () => {}
}
