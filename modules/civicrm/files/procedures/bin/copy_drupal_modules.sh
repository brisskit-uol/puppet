source ./install-config.sh
source ./set-install-paths.sh


modules_dir="${drupalcore}/sites/all/modules"

if [ ! -d "${modules_dir}" ]
then
  mkdir -p "${modules_dir}"
fi

echo "Copying drupal modules to ${modules_dir}" 
cp -uvr ../../drupal/* "${modules_dir}"
echo 
