# Facial-Recognition-System
This the main repository for the Capstone Project of IT - [ASU].


## Installation Instructions

1. Clone this repository (or download this as a zip file) and copy its contents to `C:\xampp\htdocs` (or whatever you placed your xampp installation in).
> Note that the contents must be copied, not the folder itself!
2. Open a terminal, go to the htdocs folder (`cd C:\xampp\htdocs`) and execute the command `npm install`.
> Ensure that you have installed [Node.js](https://nodejs.org/) before doing this, as the command will not work otherwise.
3. Execute `npm run build` in your terminal.
4. Open another terminal, go to the htdocs folder, and execute `py Original_code/scripts/server.py` (Make sure that you have python installed)
> If an error is thrown, saying something like `cv2 could not be resolved`, Make sure that the dependencies are installed. Try executing `pip install -r requirements.txt` first. Make sure that you are still in the htdocs directory while doing so. Then try the command again.
1. Open a browser and type [localhost](http://localhost) in the search bar.