#!/usr/bin/env bash
echo -e "\e[92m######################################################################"
echo -e "\e[92m#                                                                    #"
echo -e "\e[92m#                      Start EdroneCrm Builder                       #"
echo -e "\e[92m#                                                                    #"
echo -e "\e[92m######################################################################"
echo -e "\e[39m "
PLUGIN_ROOT="$(cd "$(dirname "$0")" && pwd)"

SHOPWARE_ROOT="$(cd "${PLUGIN_ROOT}/../../../" && pwd)"

if [ -f "${SHOPWARE_ROOT}/psh.phar" ]; then
    SW_ENV="dev"
    echo -e "\e[105mUse developer scripts to build\e[49m"
elif [ -f "${SHOPWARE_ROOT}/bin/build-storefront.sh" ]; then
    SW_ENV="prod"
    echo -e "\e[102mUse production scripts to build\e[49m"
else
  echo -e "\e[101mNie wykryto Shopware"
  exit 1
fi

# echo -e "\e[49m"
echo -e "\e[39m "
echo -e "Step 1 of 7 \e[33mRemove old release\e[39m"
cd $PLUGIN_ROOT
rm -rf CrehlerEdroneCrm CrehlerEdroneCrm-*.zip
echo -e "\e[32mOK"

echo -e "\e[39m "
echo -e "\e[39m======================================================================"
echo -e "\e[39m "
echo -e "Step 2 of 7 \e[33mCopy files\e[39m"
cd $SHOPWARE_ROOT

if [[ $SW_ENV == "dev" ]]; then
  ./psh.phar storefront:build
else
  ./bin/build-storefront.sh
fi

cd $PLUGIN_ROOT

echo -e "\e[32mOK"
echo -e "\e[39m "
echo -e "\e[39m======================================================================"
echo -e "\e[39m "
echo -e "Step 2 of 7 \e[33mCopy files\e[39m"
rsync -av --progress . CrehlerEdroneCrm --exclude CrehlerEdroneCrm
echo -e "\e[32mOK"


echo -e "\e[39m "
echo -e "\e[39m======================================================================"
echo -e "\e[39m "
echo -e "Step 3 of 7 \e[33mGo to directory\e[39m"
cd CrehlerEdroneCrm
echo -e "\e[32mOK"

echo -e "\e[39m "
echo -e "\e[39m======================================================================"
echo -e "\e[39m "
echo -e "Step 5 of 7 \e[33mDeleting unnecessary files\e[39m"
cd ..
( find ./CrehlerEdroneCrm -type d -name ".git" && find ./CrehlerEdroneCrm -name ".gitignore" && find ./CrehlerEdroneCrm -name "yarn.lock" && find ./CrehlerEdroneCrm -name ".php_cs.dist" &&  find ./CrehlerEdroneCrm -name ".gitmodules" && find ./CrehlerEdroneCrm -name "build.sh" ) | xargs rm -r
echo -e "\e[32mOK"


echo -e "\e[39m "
echo -e "\e[39m======================================================================"
echo -e "\e[39m "
echo -e "Step 6 of 7 \e[33mCreate ZIP\e[39m"
zip -rq CrehlerEdroneCrm-master.zip CrehlerEdroneCrm
echo -e "\e[32mOK"

echo -e "\e[39m "
echo -e "\e[39m======================================================================"
echo -e "\e[39m "
echo -e "Step 7 of 7 \e[33mClear build directory\e[39m"
rm -rf CrehlerEdroneCrm
echo -e "\e[32mOK"


echo -e "\e[92m######################################################################"
echo -e "\e[92m#                                                                    #"
echo -e "\e[92m#                        Build Complete                              #"
echo -e "\e[92m#                                                                    #"
echo -e "\e[92m######################################################################"
echo -e "\e[39m "
echo "   _____          _     _           ";
echo "  / ____|        | |   | |          ";
echo " | |     _ __ ___| |__ | | ___ _ __ ";
echo " | |    | '__/ _ \ '_ \| |/ _ \ '__|";
echo " | |____| | |  __/ | | | |  __/ |   ";
echo "  \_____|_|  \___|_| |_|_|\___|_|   ";
echo "                                    ";
echo "                                    ";
