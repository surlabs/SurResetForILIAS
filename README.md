### Installation steps
1. Create subdirectories, if necessary for /Customizing/global/plugins/Services/UIComponent/UserInterfaceHook/
2. In /Customizing/global/plugins/Services/UIComponent/UserInterfaceHook/
3. Then, execute:
```bash
git clone https://github.com/surlabs/SurResetForILIAS.git ./SurReset
cd SurReset
git checkout ilias9
```
4. SurReset uses the ILIAS composer autoloader functionality so, after installing or update the plugin, ensure you run on the ILIAS root folder
```bash
composer du
npm install
php setup/setup.php update
```
***
**Please ensure you don't ignore plugins on composer.json**
***
