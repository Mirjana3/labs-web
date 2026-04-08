const express = require('express');
const app = express();
const PORT = process.env.PORT || 3000;
app.get('/', (req, res) => {
    res.send('Pozdrav sa Railway servera!');
});
app.listen(PORT, () => {
    console.log('Server pokrenut na portu ${PORT}');
});