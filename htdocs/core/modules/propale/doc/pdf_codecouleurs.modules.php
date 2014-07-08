<?php
/* Copyright (C) 2004-2011 Laurent Destailleur  <eldy@users.sourceforge.net>
 * Copyright (C) 2005-2012 Regis Houssin        <regis@dolibarr.fr>
 * Copyright (C) 2008      Raphael Bertrand     <raphael.bertrand@resultic.fr>
 * Copyright (C) 2010-2012 Juanjo Menent	    <jmenent@2byte.es>
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 * or see http://www.gnu.org/
 */

/**
 *	\file       htdocs/core/modules/propale/doc/pdf_azur.modules.php
 *	\ingroup    propale
 *	\brief      Fichier de la classe permettant de generer les propales au modele Azur
 */
require_once DOL_DOCUMENT_ROOT.'/core/modules/propale/modules_propale.php';
require_once DOL_DOCUMENT_ROOT.'/product/class/product.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/company.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/functions2.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/pdf.lib.php';


/**
 *	Classe permettant de generer les propales au modele Azur
 */
class pdf_codecouleurs extends ModelePDFPropales
{
	var $db;
	var $name;
	var $description;
	var $type;

	var $phpmin = array(4,3,0); // Minimum version of PHP required by module
	var $version = 'dolibarr';

	var $page_largeur;
	var $page_hauteur;
	var $format;
	var $marge_gauche;
	var	$marge_droite;
	var	$marge_haute;
	var	$marge_basse;

	var $emetteur;	// Objet societe qui emet

	// Mise en abyme de Code Couleurs.
	var $CC_PINK = array(
		'r' => 0xB5,
		'g' => 0x5,
		'b' => 0x4D
	);
	var $CC_LIGHT_GREY = array(
		'r' => 0xDE,
		'g' => 0xE3,
		'b' => 0xE6
	);
	var $CC_DARK_GREY = array(
		'r' => 0x81,
		'g' => 0x91,
		'b' => 0x9A
	);
	var $CC_GREY_WRITE = array(
		'r' => 0x6F,
		'g' => 0x74,
		'b' => 0x79
	);
	var $BLACK = array(
		'r' => 0x0,
		'g' => 0x0,
		'b' => 0x0
	);
	var $WHITE = array(
		'r' => 0xFF,
		'g' => 0xFF,
		'b' => 0xFF
	);
	var $CC_RED = array(
		'r' => 0xC8,
		'g' => 0x0,
		'b' => 0x0
	);
	
	var $ONE_MORE_LINE = 5;
	var $FIRST_AFTER_HEADER = 48;

	/**
	 *	Constructor
	 *
	 *  @param		DoliDB		$db      Database handler
	 */
	function __construct($db)
	{
		global $conf,$langs,$mysoc;

		$langs->load("main");
		$langs->load("bills");

		$this->db = $db;
		$this->name = "codecouleurs";
		$this->description = $langs->trans('DocModelAzurDescription');

		// Dimension page pour format A4
		$this->type = 'pdf';
		$formatarray=pdf_getFormat();
		$this->page_largeur = $formatarray['width'];
		$this->page_hauteur = $formatarray['height'];
		$this->format = array($this->page_largeur,$this->page_hauteur);
		$this->marge_gauche=isset($conf->global->MAIN_PDF_MARGIN_LEFT)?$conf->global->MAIN_PDF_MARGIN_LEFT:10;
		$this->marge_droite=isset($conf->global->MAIN_PDF_MARGIN_RIGHT)?$conf->global->MAIN_PDF_MARGIN_RIGHT:10;
		$this->marge_haute =isset($conf->global->MAIN_PDF_MARGIN_TOP)?$conf->global->MAIN_PDF_MARGIN_TOP:10;
		$this->marge_basse =isset($conf->global->MAIN_PDF_MARGIN_BOTTOM)?$conf->global->MAIN_PDF_MARGIN_BOTTOM:10;

		$this->option_logo = 0;                    // Affiche logo
		$this->option_tva = 1;                     // Gere option tva FACTURE_TVAOPTION
		$this->option_modereg = 0;                 // Affiche mode reglement
		$this->option_condreg = 0;                 // Affiche conditions reglement
		$this->option_codeproduitservice = 0;      // Affiche code produit-service
		$this->option_multilang = 0;               // Dispo en plusieurs langues
		$this->option_escompte = 1;                // Affiche si il y a eu escompte
		$this->option_credit_note = 1;             // Support credit notes
		$this->option_freetext = 1;				   // Support add of a personalised text
		$this->option_draft_watermark = 1;		   //Support add of a watermark on drafts

		$this->franchise=!$mysoc->tva_assuj;

		// Get source company
		$this->emetteur=$mysoc;
		if (empty($this->emetteur->country_code)) $this->emetteur->country_code=substr($langs->defaultlang,-2);    // By default, if was not defined

		// Define position of columns                
		$this->posxdesc=$this->marge_gauche+1;
		$this->posxtva=88;
		$this->posxup=116;
		$this->posxqty=129;
		$this->posxdiscount=144;
		$this->postotalht=172;
		/*$this->posxtva=109;
		$this->posxup=130;
		$this->posxqty=143;
		$this->posxdiscount=158;
		$this->postotalht=178;*/

		if ($this->page_largeur < 210) // To work with US executive format
		{
			$this->posxtva-=20;
			$this->posxup-=20;
			$this->posxqty-=20;
			$this->posxdiscount-=20;
			$this->postotalht-=20;
		}

		$this->tva=array();
		$this->localtax1=array();
		$this->localtax2=array();
		$this->atleastoneratenotnull=0;
		$this->atleastonediscount=0;
	}

	/**
	 *  Function to build pdf onto disk
	 *
	 *  @param		Object		$object				Object to generate
	 *  @param		Translate	$outputlangs		Lang output object
	 *  @param		string		$srctemplatepath	Full path of source filename for generator using a template file
	 *  @param		int			$hidedetails		Do not show line details
	 *  @param		int			$hidedesc			Do not show desc
	 *  @param		int			$hideref			Do not show ref
	 *  @param		object		$hookmanager		Hookmanager object
	 *  @return     int             				1=OK, 0=KO
	 */
	function write_file($object,$outputlangs,$srctemplatepath='',$hidedetails=0,$hidedesc=0,$hideref=0,$hookmanager=false)
	{
		global $user,$langs,$conf;

		if (! is_object($outputlangs)) $outputlangs=$langs;
		// For backward compatibility with FPDF, force output charset to ISO, because FPDF expect text to be encoded in ISO
		if (! empty($conf->global->MAIN_USE_FPDF)) $outputlangs->charset_output='ISO-8859-1';

		$outputlangs->load("main");
		$outputlangs->load("dict");
		$outputlangs->load("companies");
		$outputlangs->load("bills");
		$outputlangs->load("propal");
		$outputlangs->load("products");

		if ($conf->propal->dir_output)
		{
			$object->fetch_thirdparty();

			// $deja_regle = 0;

			// Definition of $dir and $file
			if ($object->specimen)
			{
				$dir = $conf->propal->dir_output;
				$file = $dir . "/SPECIMEN.pdf";
			}
			else
			{
				$objectref = dol_sanitizeFileName($object->ref);
				$dir = $conf->propal->dir_output . "/" . $objectref;
				$file = $dir . "/" . $objectref . ".pdf";
			}

			if (! file_exists($dir))
			{
				if (dol_mkdir($dir) < 0)
				{
					$this->error=$langs->trans("ErrorCanNotCreateDir",$dir);
					return 0;
				}
			}

			if (file_exists($dir))
			{
				$nblignes = count($object->lines);

				// Create pdf instance
				$pdf=pdf_getInstance($this->format);
				$default_font_size = pdf_getPDFFontSize($outputlangs);	// Must be after pdf_getInstance
				$heightforinfotot = 50;	// Height reserved to output the info and total part
				$heightforfreetext= (isset($conf->global->MAIN_PDF_FREETEXT_HEIGHT)?$conf->global->MAIN_PDF_FREETEXT_HEIGHT:5);	// Height reserved to output the free text on last page
				$heightforfooter = $this->marge_basse + 8;	// Height reserved to output the footer (value include bottom margin)
				$pdf->SetAutoPageBreak(1,0);

				if (class_exists('TCPDF'))
				{
					$pdf->setPrintHeader(false);
					$pdf->setPrintFooter(false);
				}

				$ubuntu = $this->get_ubuntu_font_array($outputlangs);
				$pdf->SetFont($ubuntu['light']['normal']);
				// Set path to the background PDF File
				if (empty($conf->global->MAIN_DISABLE_FPDI) && ! empty($conf->global->MAIN_ADD_PDF_BACKGROUND))
				{
					$pagecount = $pdf->setSourceFile($conf->mycompany->dir_output.'/'.$conf->global->MAIN_ADD_PDF_BACKGROUND);
					$tplidx = $pdf->importPage(1);
				}

				$pdf->Open();
				$pagenb=0;
				$pdf->SetDrawColor($this->CC_DARK_GREY['r'],$this->CC_DARK_GREY['g'],$this->CC_DARK_GREY['b']);
				$pdf->SetDisplayMode(100);

				$pdf->SetTitle($outputlangs->convToOutputCharset($object->ref));
				$pdf->SetSubject($outputlangs->transnoentities("CommercialProposal"));
				$pdf->SetCreator("Dolibarr ".DOL_VERSION);
				$pdf->SetAuthor($outputlangs->convToOutputCharset($user->getFullName($outputlangs)));
				$pdf->SetKeyWords($outputlangs->convToOutputCharset($object->ref)." ".$outputlangs->transnoentities("CommercialProposal"));
				if (!empty($conf->global->MAIN_DISABLE_PDF_COMPRESSION)) { $pdf->SetCompression(false); }

				$pdf->SetMargins($this->marge_gauche, $this->marge_haute, $this->marge_droite);   // Left, Top, Right

				// Positionne $this->atleastonediscount si on a au moins une remise
				for ($i = 0 ; $i < $nblignes ; $i++)
				{
					if ($object->lines[$i]->remise_percent)
					{
						$this->atleastonediscount++;
					}
				}

				// New page
				$pdf->AddPage();
				if (! empty($tplidx)) $pdf->useTemplate($tplidx);
				$pagenb++;
				$current_tab_height = 0;
				$this->_pagehead($pdf, $object, 1, $outputlangs, $hookmanager);
				$pdf->SetFont($ubuntu['light']['normal'],'', $default_font_size - 1);
				$pdf->MultiCell(0, 3, '');		// Set interline to 3
				$pdf->SetTextColor($this->BLACK['r'],$this->BLACK['g'],$this->BLACK['b']);

				$tab_top_newpage = (empty($conf->global->MAIN_PDF_DONOTREPEAT_HEAD)?42:10);
				$tab_height = 130;
				
				// Affiche le titre du devis
				$tab_top = 105;
				if (!empty($object->titre))
				{
					$pdf->SetFont($ubuntu['medium']['normal'],'', $default_font_size - 1);
					$pdf->writeHTMLCell(190, 3, $this->posxdesc-1, $tab_top + 5, dol_htmlentitiesbr($object->titre), 0, 1);

					$nexY = $pdf->GetY() - 5;
					$height_note=$nexY-$tab_top;

					$tab_height = $tab_height - $height_note;
					$tab_top = $nexY+9 + $height_note;
				}
				else
				{
					$height_note=0;
				}

				$iniY = $tab_top + 7;
				$curY = $tab_top + 7;
				$nexY = $tab_top + 7;
				$tab_header_height = 6;
				$hasOptions = false;

				// Loop on each lines
				for ($i = 0 ; $i < $nblignes ; $i++)
				{
					$curY = $nexY;
					$pdf->SetFont($ubuntu['light']['normal'],'', $default_font_size - 1);   // Into loop to work with multipage
					$pdf->SetTextColor($this->BLACK['r'],$this->BLACK['g'],$this->BLACK['b']);

					// On rajoute $tab_header_height à la topMargin pour éviter d'écrire quoi que ce soit dans le header récapitulatif.
					$pdf->setTopMargin($tab_top_newpage + $tab_header_height);
					$pdf->setPageOrientation('', 1, $heightforfooter);	// The only function to edit the bottom margin of current page to set it.
					$pageposbefore=$pdf->getPage();

					// Description of product line
					$curX = $this->posxdesc-1;

					$showpricebeforepagebreak=1;

					$pdf->startTransaction();
					$this->pdf_writelinedesc($pdf, $object, $i, $this->posxup-$curX, $curY, $outputlangs);
					//pdf_writelinedesc($pdf,$object,$i,$outputlangs,$this->posxup-$curX,3,$curX,$curY,$hideref,$hidedesc,0,$hookmanager);
					$pageposafter=$pdf->getPage();
					if ($pageposafter > $pageposbefore)	// There is a pagebreak
					{
						$pdf->rollbackTransaction(true);
						// La page est finie, on trace le cadre du tableau sur toute la page.
						$draw_tab_top = ($pagenb > 1) ? $tab_top_newpage : $tab_top;
						$this->_draw_tableau_border($pdf, $draw_tab_top, $this->page_hauteur - $heightforfooter - $draw_tab_top -1);
						$current_tab_height = 0;
						
						$pageposafter=$pageposbefore;
						$pdf->setPageOrientation('', 1, $heightforfooter);	// The only function to edit the bottom margin of current page to set it.
						$this->pdf_writelinedesc($pdf, $object, $i, $this->posxup-$curX, $curY, $outputlangs);
						//pdf_writelinedesc($pdf,$object,$i,$outputlangs,$this->posxtva-$curX,4,$curX,$curY,$hideref,$hidedesc,0,$hookmanager);
						$pageposafter=$pdf->getPage();
						$posyafter=$pdf->GetY();
					}
					else	// No pagebreak
					{
						$pdf->commitTransaction();
					}

					$nexY = $pdf->GetY();
					$pageposafter=$pdf->getPage();
					$pdf->setPage($pageposbefore);
					$pdf->setTopMargin($this->marge_haute);
					$pdf->setPageOrientation('', 1, 0);	// The only function to edit the bottom margin of current page to set it.

					$pdf->SetFont($ubuntu['light']['normal'],'', $default_font_size - 1);   // On repositionne la police par defaut

					if (preg_match("/\(option\)/i", $object->lines[$i]->desc)) {
						$hasOptions = true;
					}

					// Price without VAT before discount
					if (empty($conf->global->MAIN_GENERATE_DOCUMENTS_WITHOUT_VAT))
					{
						$pdf->SetXY($this->posxup, $curY);
						$up_excl_tax = (pdf_getlineupexcltax($object, $i, $outputlangs, $hidedetails, $hookmanager)*pdf_getlineqty($object,$i,$outputlangs,$hidedetails));
						$pdf->MultiCell($this->posxup-$this->posxtva-1, 3, price($up_excl_tax), 0, 'R');
					}

					// VAT Amount
					$pdf->SetXY($this->posxdiscount-2, $curY);
					$total_ttc = $object->lines[$i]->total_ttc;
					$total_ht = $object->lines[$i]->total_ht;
					$vat_amount = price($total_ttc - $total_ht, 0, '', 0, 2, 2);
					$pdf->MultiCell($this->postotalht-$this->posxdiscount+1, 3, $vat_amount, 0, 'R');


					// Total TTC
					$total_incl_tax = pdf_getlinetotalwithtax($object, $i, $outputlangs, $hidedetails);
					$pdf->SetXY($this->postotalht, $curY);
					$pdf->MultiCell($this->page_largeur-$this->marge_droite-$this->postotalht, 3, $total_incl_tax, 0, 'R', 0);

					// Collecte des totaux par valeur de tva dans $this->tva["taux"]=total_tva
					$tvaligne=$object->lines[$i]->total_tva;
					$localtax1ligne=$object->lines[$i]->total_localtax1;
					$localtax2ligne=$object->lines[$i]->total_localtax2;

					if ($object->remise_percent) {
						$tvaligne-=($tvaligne*$object->remise_percent)/100;
						$localtax1ligne-=($localtax1ligne*$object->remise_percent)/100;
						$localtax2ligne-=($localtax2ligne*$object->remise_percent)/100;
					}

					$vatrate=(string) $object->lines[$i]->tva_tx;
					$localtax1rate=(string) $object->lines[$i]->localtax1_tx;
					$localtax2rate=(string) $object->lines[$i]->localtax2_tx;

					if (($object->lines[$i]->info_bits & 0x01) == 0x01) $vatrate.='*';
					if (! isset($this->tva[$vatrate]))				$this->tva[$vatrate]='';
					if (! isset($this->localtax1[$localtax1rate]))	$this->localtax1[$localtax1rate]='';
					if (! isset($this->localtax2[$localtax2rate]))	$this->localtax2[$localtax2rate]='';
					$this->tva[$vatrate] += $tvaligne;
					$this->localtax1[$localtax1rate]+=$localtax1ligne;
					$this->localtax2[$localtax2rate]+=$localtax2ligne;

					// We suppose that a too long description is moved completely on next page
					if ($pageposafter > $pageposbefore) {
						$pdf->setPage($pageposafter);
						$curY = $tab_top_newpage + $tab_header_height;
					}

					// Add line
					if (! empty($conf->global->MAIN_PDF_DASH_BETWEEN_LINES) && $i < ($nblignes - 1))
					{
						$pdf->SetXY($this->postotalht, $curY);
						$pdf->SetLineStyle(array(
							'width' => 0.15,
							'dash'  => '0.05,1.4',
							'color' => array($this->CC_DARK_GREY['r'], $this->CC_DARK_GREY['g'], $this->CC_DARK_GREY['b']),
							'cap'   => 'round',
							'join'  => 'round'
						));
						$pdf->line($this->marge_gauche, $nexY+1, $this->page_largeur - $this->marge_droite, $nexY+1);
						$pdf->SetLineStyle(array('dash'=>0));
					}

					$nexY+=2;    // Passe espace entre les lignes

					$current_tab_height += ($nexY - $curY);
					// Detect if some page were added automatically and output _tableau for past pages
					while ($pagenb < $pageposafter)
					{
						$pdf->setPage($pagenb);
						if ($pagenb == 1)
						{
							$this->_tableau($pdf, $tab_top, $this->page_hauteur - $tab_top - $heightforfooter -1, 0, $outputlangs, $object, 0, 1);
						}
						else
						{
							$this->_tableau($pdf, $tab_top_newpage, $this->page_hauteur - $tab_top_newpage - $heightforfooter -1, 0, $outputlangs, $object, 1, 1);
						}
						$this->_pagefoot($pdf,$object,$outputlangs,1);
						$pagenb++;
						$pdf->setPage($pagenb);
						$pdf->setPageOrientation('', 1, 0);	// The only function to edit the bottom margin of current page to set it.
						if (empty($conf->global->MAIN_PDF_DONOTREPEAT_HEAD)) {
							$this->_pagehead($pdf, $object, 0, $outputlangs, $hookmanager);
						}
					}
					if (isset($object->lines[$i+1]->pagebreak) && $object->lines[$i+1]->pagebreak)
					{
						if ($pagenb == 1)
						{
							$this->_tableau($pdf, $tab_top, $this->page_hauteur - $tab_top - $heightforfooter, 0, $outputlangs, $object, 0, 1);
						}
						else
						{
							$this->_tableau($pdf, $tab_top_newpage, $this->page_hauteur - $tab_top_newpage - $heightforfooter, 0, $outputlangs, $object, 1, 1);
						}
						$this->_pagefoot($pdf,$object,$outputlangs,1);
						// New page
						$pdf->AddPage();
						if (! empty($tplidx)) $pdf->useTemplate($tplidx);
						$pagenb++;
						if (empty($conf->global->MAIN_PDF_DONOTREPEAT_HEAD)) {
							$this->_pagehead($pdf, $object, 0, $outputlangs, $hookmanager);
						}
					}
				}

				// Show square (on trace le tableau de la dernière page)
				$draw_tab_top = ($pagenb == 1) ? $tab_top : $tab_top_newpage;
				$current_tab_height += $this->_tableau($pdf, $draw_tab_top, $current_tab_height + $tab_header_height, 0, $outputlangs, $object, $pagenb != 1, 0);
				$this->_draw_tableau_border($pdf, $draw_tab_top, $current_tab_height);
				
				// Placement pour les infos suivantes
				$end_tab_y = $current_tab_height + $draw_tab_top;
				$space_tab_tot = 10;
				$bottom_max = $this->page_hauteur - $heightforfreetext - $heightforfooter + 1;
				
				if ($end_tab_y + $this->ONE_MORE_LINE > $bottom_max) {
					// Pas de place pour les totaux, les infos et les mentions client, on ajoute une page pour les écrire à la page suivante
					$pdf->AddPage();
					if (!empty($tplidx)) {
						$pdf->useTemplate($tplidx);
					}
					if (empty($conf->global->MAIN_PDF_DONOTREPEAT_HEAD)) {
						$this->_pagehead($pdf, $object, 0, $outputlangs, $hookmanager);
					}
					$bottomlasttab = $this->FIRST_AFTER_HEADER;	// Haut de la page en +
				}
				else {
					// On écrit en bas de la page, quitte à rogner sur l'espace minimal.
					$bottomlasttab = min($end_tab_y + $space_tab_tot, $bottom_max);
				}
				
				$pdf->startTransaction();
				$posy = $this->_notes_post_tableau($pdf, $object, $bottomlasttab, $outputlangs, $hasOptions);
				
				if ($posy > $bottom_max) {
					// Pas de place en bas de la page, on réécrit en haut de la suivante.
					$pdf->rollbackTransaction(true);
					
					$pdf->AddPage();
					if (!empty($tplidx)) {
						$pdf->useTemplate($tplidx);
					}
					if (empty($conf->global->MAIN_PDF_DONOTREPEAT_HEAD)) {
						$this->_pagehead($pdf, $object, 0, $outputlangs, $hookmanager);
					}
					$posy = $this->_notes_post_tableau($pdf, $object, $this->FIRST_AFTER_HEADER, $outputlangs, $hasOptions);
				}
				else {
					$pdf->commitTransaction();
				}

				// Pied de page
				for ($page = 1; $page <= $pdf->getNumPages(); $page++) {
					$pdf->setPage($page);
					$this->_pagefoot($pdf,$object,$outputlangs);
				}
				
				if (method_exists($pdf,'AliasNbPages')) { $pdf->AliasNbPages(); }

				$pdf->Close();

				$pdf->Output($file,'F');

				//Add pdfgeneration hook
				if (! is_object($hookmanager))
				{
					include_once DOL_DOCUMENT_ROOT.'/core/class/hookmanager.class.php';
					$hookmanager=new HookManager($this->db);
				}
				$hookmanager->initHooks(array('pdfgeneration'));
				$parameters=array('file'=>$file,'object'=>$object,'outputlangs'=>$outputlangs);
				global $action;
				$reshook=$hookmanager->executeHooks('afterPDFCreation',$parameters,$this,$action);    // Note that $action and $object may have been modified by some hooks

				if (! empty($conf->global->MAIN_UMASK))
				@chmod($file, octdec($conf->global->MAIN_UMASK));

				return 1;   // Pas d'erreur
			}
			else
			{
				$this->error=$langs->trans("ErrorCanNotCreateDir",$dir);
				return 0;
			}
		}
		else
		{
			$this->error=$langs->trans("ErrorConstantNotDefined","PROP_OUTPUTDIR");
			return 0;
		}

		$this->error=$langs->trans("ErrorUnknown");
		return 0;   // Erreur par defaut
	}
	
	/**
	 * Écrire la désignation. Cette méthode est utilisée en lieu et place de la
	 * fonction pdf_writelinedesc(); de Dolibarr car cette dernière ne permet pas
	 * d'avoir le rendu souhaité.
	 * @param PDF $pdf
	 * @param Propal $object The propale
	 * @param int $w Column width
	 * @param int $posy Y
	 * @param mixed $outputlangs Lang manager
	 */
	function pdf_writelinedesc(&$pdf, $object, $i, $w, $posy, $outputlangs) {
		global $langs;

		if (!is_object($outputlangs)) {
			$outputlangs = $langs;
		}
		
		$ubuntu = $this->get_ubuntu_font_array($outputlangs);
		$default_size = pdf_getPDFFontSize($outputlangs);
		
		$description = trim($object->lines[$i]->desc);
		$retrait = 4;
		
		// Extraire la prmière ligne
		$ligne1 = trim(html_entity_decode(strtok(htmlentities($description), "\n")));
		
		// Écrire la première ligne
		$pdf->SetFont($ubuntu['regular']['normal'], '', $default_size - 1);
		$pdf->SetTextColor($this->BLACK['r'],$this->BLACK['g'],$this->BLACK['b']);
		$pdf->writeHTMLCell($w, $this->ONE_MORE_LINE, $this->posxdesc-1, $posy, dol_htmlentitiesbr($ligne1), 0, 1);
		
		// Avoir le reste du texte
		if ($description != $ligne1) {
			$regex_debut = "/".preg_quote(html_entity_decode($ligne1), '/')."/A";
			$reste = trim(preg_replace($regex_debut, '', $description, 1));

			// Écrire le reste du texte
			$pdf->SetFont($ubuntu['light']['normal'], '', $default_size - 2);
			$pdf->SetTextColor($this->CC_GREY_WRITE['r'],$this->CC_GREY_WRITE['g'],$this->CC_GREY_WRITE['b']);
			$pdf->writeHTMLCell(
				$w - $retrait, $this->ONE_MORE_LINE, $this->posxdesc-1 + $retrait, $pdf->GetY() + 1,
				dol_htmlentitiesbr($reste),
				0, 1
			);

			$pdf->SetTextColor($this->BLACK['r'],$this->BLACK['g'],$this->BLACK['b']);
		}
		return $pdf->GetY();
	}
	
	/**
	 * Affichage des notes et des mentions client
	 * @param PDF $pdf PDF à écrire.
	 * @param Propal $object La propale
	 * @param int $posy Ordonnée du document à partir de laquelle on commence
	 * à écrire les notes.
	 * @param Lang $outputlangs Gestion des langues
	 * @return int L'ordonnée finale.
	 */
	function _notes_post_tableau(&$pdf, $object, $posy, $outputlangs, $hasOptions = false) {
		// Affiche zone infos
		$posy_gauche=$this->_tableau_info($pdf, $object, $posy, $outputlangs);
		$posy_gauche=$this->_affichage_notes_et_mentions($pdf, $object, $posy_gauche, $outputlangs);

		// Affiche zone totaux
		$posy_droit=$this->_tableau_tot($pdf, $object, 0, $posy, $outputlangs, $hasOptions);
		
		return max($posy_gauche, $posy_droit);
	}
	
	/**
	 * Affichage des notes publiques et des mentions liées aux clients
	 *   @param		PDF			&$pdf     		Object PDF
	 *   @param		Object		$object			Object to show
	 *   @param		int			$posy			Y
	 *   @param		Translate	$outputlangs	Langs object
	 *   @return	int			New Y
	 */
	function _affichage_notes_et_mentions(&$pdf, $object, $posy, $outputlangs) {
		// Le contenu est affiché dans les notes publiques
		
		// Pas de notes : on ne fait rien.
		if ($object->note_public === '') {
			return $posy;
		}
		
		$col_width = 110 - $this->marge_gauche;
		
		$ubuntu = $this->get_ubuntu_font_array($outputlangs);
		$default_font_size = pdf_getPDFFontSize($outputlangs);

		$pdf->SetFont($ubuntu['light']['normal'],'', $default_font_size - 2);
		$pdf->SetTextColor($this->BLACK['r'],$this->BLACK['g'],$this->BLACK['b']);
		$pdf->writeHTMLCell(
			$col_width, $this->ONE_MORE_LINE, $this->posxdesc-1, $posy, dol_htmlentitiesbr($object->note_public),
			0, 1);
		$nexY = $pdf->GetY();
		$height_note=$nexY-$posy;
		$posy = $nexY+9 + $height_note;
		return $posy;
	}

	/**
	 *  Show payments table
	 *
	 *  @param	PDF			&$pdf           Object PDF
	 *  @param  Object		$object         Object proposal
	 *  @param  int			$posy           Position y in PDF
	 *  @param  Translate	$outputlangs    Object langs for output
	 *  @return int             			<0 if KO, >0 if OK
	 */
	function _tableau_versements(&$pdf, $object, $posy, $outputlangs) {}


	/**
	 *   Show miscellaneous information (payment mode, payment term, ...)
	 *
	 *   @param		PDF			&$pdf     		Object PDF
	 *   @param		Object		$object			Object to show
	 *   @param		int			$posy			Y
	 *   @param		Translate	$outputlangs	Langs object
	 *   @return	void
	 */
	function _tableau_info(&$pdf, $object, $posy, $outputlangs)
	{
		global $conf;
		
		$ubuntu = $this->get_ubuntu_font_array($outputlangs);
		$default_font_size = pdf_getPDFFontSize($outputlangs);

		$pdf->SetFont($ubuntu['light']['normal'],'', $default_font_size - 1);
		
		$col_width = 110 - $this->marge_gauche;

		// If France, show VAT mention if not applicable
		if ($this->emetteur->pays_code == 'FR' && $this->franchise == 1)
		{
			$pdf->SetFont($ubuntu['bold']['normal'],'B', $default_font_size - 2);
			$pdf->SetXY($this->marge_gauche, $posy+1);
			$pdf->MultiCell($col_width, 4, $outputlangs->transnoentities("VATIsNotUsedForInvoice"), 0, 'L', 0);

			$posy=$pdf->GetY()+6;
		}

		$posxval=54;

		// Show payments conditions
		if (empty($conf->global->PROPALE_PDF_HIDE_PAYMENTTERMCOND) && ($object->cond_reglement_code || $object->cond_reglement))
		{
			$pdf->SetFont($ubuntu['medium']['normal'],'B', $default_font_size - 1);
			$pdf->SetXY($this->marge_gauche, $posy+1);
			$titre = $outputlangs->transnoentities("PaymentConditions").' :';
			$pdf->MultiCell($col_width, $this->ONE_MORE_LINE, $titre, 0, 'L');

			$pdf->SetFont($ubuntu['light']['normal'],'', $default_font_size - 1);
			$pdf->SetXY($this->marge_gauche, $posy + $this->ONE_MORE_LINE +1);
			$lib_condition_paiement=$outputlangs->transnoentities("PaymentCondition".$object->cond_reglement_code)!=('PaymentCondition'.$object->cond_reglement_code)?$outputlangs->transnoentities("PaymentCondition".$object->cond_reglement_code):$outputlangs->convToOutputCharset($object->cond_reglement_doc);
			$lib_condition_paiement=str_replace('\n',"\n",$lib_condition_paiement);
			$lib_condition_paiement = "acompte de 30% à la signature du devis";
			$pdf->MultiCell($col_width, $this->ONE_MORE_LINE, $lib_condition_paiement,0,'L');

			$posy = $pdf->GetY() + 6;
		}

		return $posy;
	}


	/**
	 *	Show total to pay
	 *
	 *	@param	PDF			&$pdf           Object PDF
	 *	@param  Facture		$object         Object invoice
	 *	@param  int			$deja_regle     Montant deja regle
	 *	@param	int			$posy			Position depart
	 *	@param	Translate	$outputlangs	Objet langs
	 *	@return int							Position pour suite
	 */
	function _tableau_tot(&$pdf, $object, $deja_regle, $posy, $outputlangs, $hasOptions = false)
	{
		global $conf,$mysoc;
		
		$ubuntu = $this->get_ubuntu_font_array($outputlangs);
		$default_font_size = pdf_getPDFFontSize($outputlangs);

		$tab2_top = $posy;
		$text_height = $this->ONE_MORE_LINE -1;
		$marguin = 1;
		$tab2_hl = $text_height + 2*$marguin;
		$pdf->SetFont($ubuntu['regular']['normal'],'', $default_font_size - 1);

		// Tableau total
		$col1x = 120; $col2x = 170;
		if ($this->page_largeur < 210) // To work with US executive format
		{
			$col2x-=20;
		}
		$largcol2 = ($this->page_largeur - $this->marge_droite - $col2x);

		$useborder=0;
		$cur_posy = $tab2_top;

		// Total HT
		$pdf->SetFillColor($this->WHITE['r'], $this->WHITE['g'], $this->WHITE['b']);
		$pdf->SetXY($col1x, $cur_posy);
		$pdf->MultiCell($col2x-$col1x, $tab2_hl, '', 0, 'L', 1);
		$pdf->SetXY($col1x, $cur_posy + $marguin);
		$pdf->MultiCell($col2x-$col1x, $text_height, $outputlangs->transnoentities("TotalHT"), 0, 'L');

		$pdf->SetXY($col2x, $cur_posy);
		$pdf->MultiCell($largcol2, $tab2_hl, '', 0, 'R', 1);
		$pdf->SetXY($col2x, $cur_posy + $marguin);
		$pdf->MultiCell($largcol2, $text_height, price($object->total_ht + (! empty($object->remise)?$object->remise:0)), 0, 'R');

		// Show VAT by rates and total
		$this->atleastoneratenotnull=0;
		if (empty($conf->global->MAIN_GENERATE_DOCUMENTS_WITHOUT_VAT))
		{
			$tvaisnull=(!empty($this->tva) AND count($this->tva) == 1 AND isset($this->tva['0.000']) AND is_float($this->tva['0.000']));
			if (empty($conf->global->MAIN_GENERATE_DOCUMENTS_WITHOUT_VAT_ISNULL) OR !$tvaisnull)
			{
				foreach($this->tva as $tvakey => $tvaval)
				{
					if ($tvakey > 0)    // On affiche pas taux 0
					{
						$this->atleastoneratenotnull++;
						$cur_posy += $tab2_hl;

						$tvacompl='';
						if (preg_match('/\*/',$tvakey))
						{
							$tvakey=str_replace('*','',$tvakey);
							$tvacompl = " (".$outputlangs->transnoentities("NonPercuRecuperable").")";
						}
						$totalvat =$outputlangs->transnoentities("TotalVAT").' ';
						$totalvat.=vatrate($tvakey,1).$tvacompl;
						$pdf->SetXY($col1x, $cur_posy + $marguin);
						$pdf->MultiCell($col2x-$col1x, $text_height, $totalvat, 0, 'L');

						$pdf->SetXY($col2x, $cur_posy + $marguin);
						$pdf->MultiCell($largcol2, $text_height, price($tvaval), 0, 'R');
					}
				}

				if (! $this->atleastoneratenotnull) // If no vat at all
				{
					$cur_posy += $tab2_hl;
					$pdf->SetXY($col1x, $cur_posy + $marguin);
					$pdf->MultiCell($col2x-$col1x, $text_height, $outputlangs->transnoentities("TotalVAT"), 0, 'L');

					$pdf->SetXY($col2x, $cur_posy + $marguin);
					$pdf->MultiCell($largcol2, $text_height, price($object->total_tva), 0, 'R');

					// Total LocalTax1
					if (! empty($conf->global->FACTURE_LOCAL_TAX1_OPTION) && $conf->global->FACTURE_LOCAL_TAX1_OPTION=='localtax1on' && $object->total_localtax1>0)
					{
						$cur_posy += $tab2_hl;
						$pdf->SetXY($col1x, $cur_posy + $marguin);
						$pdf->MultiCell($col2x-$col1x, $text_height, $outputlangs->transnoentities("TotalLT1".$mysoc->country_code), $useborder, 'L');
						$pdf->SetXY($col2x, $cur_posy + $marguin);
						$pdf->MultiCell($largcol2, $text_height, price($object->total_localtax1), $useborder, 'R');
					}

					// Total LocalTax2
					if (! empty($conf->global->FACTURE_LOCAL_TAX2_OPTION) && $conf->global->FACTURE_LOCAL_TAX2_OPTION=='localtax2on' && $object->total_localtax2>0)
					{
						$cur_posy += $tab2_hl;
						$pdf->SetXY($col1x, $cur_posy + $marguin);
						$pdf->MultiCell($col2x-$col1x, $text_height, $outputlangs->transnoentities("TotalLT2".$mysoc->country_code), $useborder, 'L');
						$pdf->SetXY($col2x, $cur_posy + $marguin);
						$pdf->MultiCell($largcol2, $text_height, price($object->total_localtax2), $useborder, 'R');
					}
				}
				else
				{
					if (! empty($conf->global->FACTURE_LOCAL_TAX1_OPTION) && $conf->global->FACTURE_LOCAL_TAX1_OPTION=='localtax1on')
					{
						//Local tax 1
						foreach($this->localtax1 as $tvakey => $tvaval)
						{
							if ($tvakey!=0)    // On affiche pas taux 0
							{
								$cur_posy += $tab2_hl;
								$pdf->SetXY($col1x, $cur_posy + $marguin);

								$tvacompl='';
								if (preg_match('/\*/',$tvakey))
								{
									$tvakey=str_replace('*','',$tvakey);
									$tvacompl = " (".$outputlangs->transnoentities("NonPercuRecuperable").")";
								}
								$totalvat = $outputlangs->transnoentities("TotalLT1".$mysoc->country_code).' ';
								$totalvat.=vatrate($tvakey,1).$tvacompl;
								$pdf->MultiCell($col2x-$col1x, $text_height, $totalvat, 0, 'L');

								$pdf->SetXY($col2x, $cur_posy + $marguin);
								$pdf->MultiCell($largcol2, $text_height, price($tvaval), 0, 'R');
							}
						}
					}

					if (! empty($conf->global->FACTURE_LOCAL_TAX2_OPTION) && $conf->global->FACTURE_LOCAL_TAX2_OPTION=='localtax2on')
					{
						//Local tax 2
						foreach($this->localtax2 as $tvakey => $tvaval)
						{
							if ($tvakey!=0)    // On affiche pas taux 0
							{
								$cur_posy += $tab2_hl;
								$pdf->SetXY($col1x, $cur_posy + $marguin);

								$tvacompl='';
								if (preg_match('/\*/',$tvakey))
								{
									$tvakey=str_replace('*','',$tvakey);
									$tvacompl = " (".$outputlangs->transnoentities("NonPercuRecuperable").")";
								}
								$totalvat =$outputlangs->transnoentities("TotalLT2".$mysoc->country_code).' ';
								$totalvat.=vatrate($tvakey,1).$tvacompl;
								$pdf->MultiCell($col2x-$col1x, $text_height, $totalvat, 0, 'L');

								$pdf->SetXY($col2x, $cur_posy + $marguin);
								$pdf->MultiCell($largcol2, $text_height, price($tvaval), 0, 'R');

							}
						}
					}
				}

				// Total TTC
				$cur_posy += $tab2_hl;
				$pdf->SetTextColor($this->BLACK['r'], $this->BLACK['g'], $this->BLACK['b']);
				
				$pdf->SetXY($col1x, $cur_posy);
				$pdf->SetFillColor($this->CC_LIGHT_GREY['r'], $this->CC_LIGHT_GREY['g'], $this->CC_LIGHT_GREY['b']);
				$pdf->MultiCell($col2x-$col1x, $tab2_hl, '', $useborder, 'L', 1);
				$pdf->SetFillColor($this->WHITE['r'], $this->WHITE['g'], $this->WHITE['b']);
				$pdf->SetXY($col1x, $cur_posy + $marguin);
				$pdf->MultiCell($col2x-$col1x, $text_height, $outputlangs->transnoentities("TotalTTC"), $useborder, 'L');

				$pdf->SetXY($col2x, $cur_posy);
				$pdf->SetFillColor($this->CC_LIGHT_GREY['r'], $this->CC_LIGHT_GREY['g'], $this->CC_LIGHT_GREY['b']);
				$pdf->MultiCell($largcol2, $tab2_hl, '', $useborder, 'L', 1);
				$pdf->SetFillColor($this->WHITE['r'], $this->WHITE['g'], $this->WHITE['b']);
				$pdf->SetXY($col2x, $cur_posy + $marguin);
				$pdf->MultiCell($largcol2, $text_height, price($object->total_ttc), $useborder, 'R');
			}
		}

		$pdf->SetTextColor($this->BLACK['r'],$this->BLACK['g'],$this->BLACK['b']);

		if ($deja_regle > 0)
		{
			$cur_posy += $tab2_hl;

			$pdf->SetXY($col1x, $cur_posy + $marguin);
			$pdf->MultiCell($col2x-$col1x, $tab2_hl, $outputlangs->transnoentities("AlreadyPaid"), 0, 'L', 0);

			$pdf->SetXY($col2x, $cur_posy + $marguin);
			$pdf->MultiCell($largcol2, $text_height, price($deja_regle), 0, 'R', 0);

			$cur_posy += $tab2_hl;
			$pdf->SetTextColor($this->BLACK['r'],$this->BLACK['g'],$this->BLACK['b']);
			$pdf->SetFillColor($this->CC_LIGHT_GREY['r'],$this->CC_LIGHT_GREY['g'],$this->CC_LIGHT_GREY['b']);
			$pdf->SetXY($col1x, $cur_posy);
			$pdf->MultiCell($col2x-$col1x, $tab2_hl, '', $useborder, 'L', 1);
			$pdf->SetXY($col1x, $cur_posy + $marguin);
			$pdf->MultiCell($col2x-$col1x, $text_height, $outputlangs->transnoentities("RemainderToPay"), $useborder, 'L');

			$pdf->SetXY($col2x, $cur_posy);
			$pdf->MultiCell($largcol2, $tab2_hl, '', $useborder, 'R', 1);
			$pdf->SetXY($col2x, $cur_posy + $marguin);
			$pdf->MultiCell($largcol2, $text_height, price($object->total_ttc - $deja_regle), $useborder, 'R');

			$pdf->SetTextColor($this->BLACK['r'],$this->BLACK['g'],$this->BLACK['b']);
		}
		
		$pdf->SetFont($ubuntu['light']['normal'],'', $default_font_size - 3);

		$cur_posy += $tab2_hl;
		
		if ($hasOptions) {
			$pdf->SetXY($col1x, ++$cur_posy);
			$pdf->MultiCell($col2x-$col1x, $text_height, $outputlangs->transnoentities('AllOptions'), $useborder, 'L');
		}
		
		$pdf->SetFont($ubuntu['light']['normal'],'', $default_font_size - 1);

		$cur_posy += $tab2_hl;
		return $cur_posy;
	}
	
	/**
	 *   Dessine les bords du tableau pour chaque page
	 *   @param		PDF			&$pdf     		Object PDF
	 *   @param		string		$tab_top		Top position of table
	 *   @param		string		$tab_height		Height of table (rectangle)
	 *   @param		int			$hidetop		1=Hide top bar of array and title, 0=Hide nothing, -1=Hide only title
	 *   @param		int			$hidebottom		Hide bottom bar of array
	 */
	function _draw_tableau_border(&$pdf, $tab_top, $tab_height, $hidetop=0, $hidebottom=0) {
		// Force to disable hidetop and hidebottom
		$hidebottom=0;
		if ($hidetop) { $hidetop=0; }
		
		// Output Rect
		$pdf->SetDrawColor($this->CC_DARK_GREY['r'], $this->CC_DARK_GREY['g'], $this->CC_DARK_GREY['b']);
		// Rect prend une longueur en 3eme param et 4eme param
		$this->printRect($pdf,
			$this->marge_gauche, $tab_top,
			$this->page_largeur-$this->marge_gauche-$this->marge_droite, $tab_height, 
			$hidetop, $hidebottom
		);
	}

	/**
	 *   Show table for lines
	 *
	 *   @param		PDF			&$pdf     		Object PDF
	 *   @param		string		$tab_top		Top position of table
	 *   @param		string		$tab_height		Height of table (rectangle)
	 *   @param		int			$nexY			Y (not used)
	 *   @param		Translate	$outputlangs	Langs object
	 *   @param		Propal		$object			The propale, used to calculte its VAT rate
	 *   @param		int			$hidetop		1=Hide top bar of array and title, 0=Hide nothing, -1=Hide only title
	 *   @param		int			$hidebottom		Hide bottom bar of array
	 *   @return	int			La hauteur du header
	 */
	function _tableau(&$pdf, $tab_top, $tab_height, $nexY, $outputlangs, $object, $hidetop=0, $hidebottom=0)
	{
		global $conf;

		$outputlangs->load('cc_extras');

		// Force to disable hidetop and hidebottom
		$hidebottom=0;
		if ($hidetop) { $hidetop=0; }

		$ubuntu = $this->get_ubuntu_font_array($outputlangs);
		$default_font_size = pdf_getPDFFontSize($outputlangs);

		// Amount in (at tab_top - 1)
		if (empty($hidetop))
		{
			$pdf->SetTextColor($this->BLACK['r'],$this->BLACK['g'],$this->BLACK['b']);
			$pdf->SetFont($ubuntu['light']['normal'],'',$default_font_size - 2);
			$titre = $outputlangs->transnoentities("AmountInCurrency",$outputlangs->transnoentitiesnoconv("Currency".$conf->currency));
			$pdf->SetXY($this->page_largeur - $this->marge_droite - ($pdf->GetStringWidth($titre) + 3), $tab_top - $this->ONE_MORE_LINE -1);
			$pdf->MultiCell(($pdf->GetStringWidth($titre) + 3), 2, $titre);
		}

		$pdf->SetFont($ubuntu['regular']['normal'],'',$default_font_size);
		$pdf->SetTextColor($this->WHITE['r'],$this->WHITE['g'],$this->WHITE['b']);

		// Tab header rectangle
		$legend_tab_height = 2;
		$pdf->SetFillColor($this->CC_DARK_GREY['r'], $this->CC_DARK_GREY['g'], $this->CC_DARK_GREY['b']);
		$pdf->SetXY($this->marge_gauche, $tab_top);
		$pdf->MultiCell($this->page_largeur-$this->marge_gauche-$this->marge_droite, $legend_tab_height + 4, '', 1, 'R', true);

		if (empty($hidetop))
		{
			$pdf->line($this->marge_gauche, $tab_top+5, $this->page_largeur-$this->marge_droite, $tab_top+5);	// line prend une position y en 2eme param et 4eme param

			$pdf->SetXY($this->posxdesc-1, $tab_top+1);
			$pdf->MultiCell(108,$legend_tab_height, $outputlangs->transnoentities("Designation"),'','L');
		}

		if (empty($conf->global->MAIN_GENERATE_DOCUMENTS_WITHOUT_VAT))
		{
			$pdf->line($this->posxup-1, $tab_top, $this->posxup-1, $tab_top + $tab_height);
			if (empty($hidetop))
			{
				$pdf->SetXY($this->posxup-4, $tab_top+1);
				$pdf->MultiCell($this->posxup-$this->posxtva+3, $legend_tab_height, $outputlangs->transnoentities("PrixHT"),'','R');
			}
		}

		$pdf->line($this->posxdiscount-1, $tab_top, $this->posxdiscount-1, $tab_top + $tab_height);
		if (empty($hidetop))
		{
			// Calcul du taux de TVA
			if (empty($object->total_ht)) {
				$object->fetch($object->id);
			}

			if ($object->total_ht == 0) {
				// Dur de calculer, on ne donne pas de %tage
				$percent = '';
			}
			else {
				$taux_tva = 100 * floatval($object->total_tva) / floatval($object->total_ht);
				$percent = sprintf(" %.1f %%", floatval($taux_tva));
			}

			$pdf->SetXY($this->posxdiscount-2, $tab_top+1);
			$pdf->MultiCell($this->postotalht-$this->posxdiscount+1, $legend_tab_height, sprintf("TVA%s", $percent),'','R');
		}

		$pdf->line($this->postotalht-1, $tab_top, $this->postotalht-1, $tab_top + $tab_height);
		if (empty($hidetop))
		{
			$pdf->SetXY($this->postotalht-2, $tab_top+1);
			$pdf->MultiCell(30, $legend_tab_height, $outputlangs->transnoentities("PrixTTC"),'','R');
		}

		$pdf->SetTextColor($this->BLACK['r'],$this->BLACK['g'],$this->BLACK['b']);	// Back to Black pour la suite
		
		return $legend_tab_height + 4;
	}

	/**
	 *  Show top header of page.
	 *
	 *  @param	PDF			&$pdf     		Object PDF
	 *  @param  Object		$object     	Object to show
	 *  @param  int	    	$showaddress    0=no, 1=yes
	 *  @param  Translate	$outputlangs	Object lang for output
	 *  @param	object		$hookmanager	Hookmanager object
	 *  @return	void
	 */
	function _pagehead(&$pdf, $object, $showaddress, $outputlangs, $hookmanager)
	{
		global $conf,$langs;

		$outputlangs->load("main");
		$outputlangs->load("bills");
		$outputlangs->load("propal");
		$outputlangs->load("companies");

		$ubuntu = $this->get_ubuntu_font_array($outputlangs);
		$default_font_size = pdf_getPDFFontSize($outputlangs);

		pdf_pagehead($pdf,$outputlangs,$this->page_hauteur);

		// Show Draft Watermark
		if($object->statut==0 && (! empty($conf->global->PROPALE_DRAFT_WATERMARK)) )
		{
			pdf_watermark($pdf,$outputlangs,$this->page_hauteur,$this->page_largeur,'mm',$conf->global->PROPALE_DRAFT_WATERMARK);
		}

		$pdf->SetTextColor($this->BLACK['r'],$this->BLACK['g'],$this->BLACK['b']);
		$pdf->SetFont($ubuntu['bold']['normal'],'B', $default_font_size);

		$posy=$this->marge_haute;
		$posx=$this->page_largeur-$this->marge_droite-100;

		$pdf->SetXY($this->marge_gauche,$posy);

		// Logo
		$logo=$conf->mycompany->dir_output.'/logos/'.$this->emetteur->logo;
		if ($this->emetteur->logo)
		{
			if (is_readable($logo))
			{
				$height=pdf_getHeightForLogo($logo);
				$pdf->Image($logo, $this->marge_gauche, $posy, 0, $height);	// width=0 (auto)
			}
			else
			{
				$pdf->SetTextColor($this->CC_RED['r'],$this->CC_RED['g'],$this->CC_RED['b']);
				$pdf->SetFont($ubuntu['bold']['normal'],'B',$default_font_size - 2);
				$pdf->MultiCell(100, 3, $outputlangs->transnoentities("ErrorLogoFileNotFound",$logo), 0, 'L');
				$pdf->MultiCell(100, 3, $outputlangs->transnoentities("ErrorGoToGlobalSetup"), 0, 'L');
			}
		}
		else
		{
			$text=$this->emetteur->name;
			$pdf->MultiCell(100, 4, $outputlangs->convToOutputCharset($text), 0, 'L');
		}

		$pdf->SetFont($ubuntu['bold']['normal'], 'B', $default_font_size);
		$pdf->SetXY($posx,$posy);
		$pdf->SetTextColor($this->BLACK['r'],$this->BLACK['g'],$this->BLACK['b']);
		$title=$outputlangs->transnoentities("CommercialProposal");
		$pdf->MultiCell(100, 4, $title, '', 'R');

		$pdf->SetFont($ubuntu['bold']['normal']);

		$posy+=$this->ONE_MORE_LINE;
		$pdf->SetXY($posx,$posy);
		$pdf->SetTextColor($this->BLACK['r'],$this->BLACK['g'],$this->BLACK['b']);
		$pdf->MultiCell(100, 4, $outputlangs->transnoentities("Ref")." : " . $outputlangs->convToOutputCharset($object->ref), '', 'R');

		$pdf->SetFont($ubuntu['light']['normal']);

		if ($object->ref_client)
		{
			$posy+=$this->ONE_MORE_LINE;
			$pdf->SetXY($posx,$posy);
			$pdf->SetTextColor($this->BLACK['r'],$this->BLACK['g'],$this->BLACK['b']);
			$pdf->MultiCell(100, 3, $outputlangs->transnoentities("RefCustomer")." : " . $outputlangs->convToOutputCharset($object->ref_client), '', 'R');
		}

		$posy+=$this->ONE_MORE_LINE;
		$pdf->SetXY($posx,$posy);
		$pdf->SetTextColor($this->BLACK['r'],$this->BLACK['g'],$this->BLACK['b']);
		$pdf->MultiCell(100, 3, $outputlangs->transnoentities("Date")." : " . dol_print_date($object->date,"day",false,$outputlangs,true), '', 'R');

		$posy+=$this->ONE_MORE_LINE;
		$pdf->SetXY($posx,$posy);
		$pdf->SetTextColor($this->BLACK['r'],$this->BLACK['g'],$this->BLACK['b']);
		$pdf->MultiCell(100, 3, $outputlangs->transnoentities("DateEndPropal")." : " . dol_print_date($object->fin_validite,"day",false,$outputlangs,true), '', 'R');

		if ($object->client->code_client)
		{
			$posy+=$this->ONE_MORE_LINE;
			$pdf->SetXY($posx,$posy);
			$pdf->SetTextColor($this->BLACK['r'],$this->BLACK['g'],$this->BLACK['b']);
			$pdf->MultiCell(100, 3, $outputlangs->transnoentities("CustomerCode")." : " . $outputlangs->transnoentities($object->client->code_client), '', 'R');
		}

		$posy+=2;

		// Show list of linked objects
		$posy = pdf_writeLinkedObjects($pdf, $object, $outputlangs, $posx, $posy, 100, 3, 'R', $default_font_size, $hookmanager);

		if ($showaddress)
		{
			$address_boxes_posy = $this->FIRST_AFTER_HEADER;
			$retrait_cadre = 3;
			
			// Sender properties
			
			// Add internal contact of proposal if defined
			$arrayidcontact=$object->getIdContact('internal','SALESREPFOLL');
			if (count($arrayidcontact) > 0)
			{
				$object->fetch_user($arrayidcontact[0]);
				$contact_emetteur = $object->user;
			}
			else {
				$contact_emetteur = null;
			}

			//$carac_emetteur .= pdf_build_address($outputlangs,$this->emetteur);
			$carac_emetteur = $this->pdf_build_address($outputlangs, $this->emetteur, $contact_emetteur, 'internal');
			
			// Show sender
			$posy=$address_boxes_posy;
			$posx=$this->marge_gauche;
			if (! empty($conf->global->MAIN_INVERT_SENDER_RECIPIENT)) { $posx=$this->page_largeur-$this->marge_droite-80; }
			$hautcadre=43;

			// Show sender frame
			$pdf->SetTextColor($this->CC_PINK['r'],$this->CC_PINK['g'],$this->CC_PINK['b']);
			$pdf->SetFont($ubuntu['regular']['normal'],'', $default_font_size);
			$pdf->SetXY($posx,$posy-6);
			$pdf->MultiCell(66,5, $outputlangs->transnoentities("BillFrom"), 0, 'L');
			$pdf->SetXY($posx,$posy);
			$pdf->SetFillColor($this->CC_LIGHT_GREY['r'],$this->CC_LIGHT_GREY['g'],$this->CC_LIGHT_GREY['b']);
			$pdf->MultiCell(82, $hautcadre, "", 0, 'R', 1);

			// Show sender name
			$pdf->SetXY($posx+$retrait_cadre,$posy+3);
			$pdf->SetFont($ubuntu['bold']['normal'],'B', $default_font_size);
			$pdf->SetTextColor($this->BLACK['r'],$this->BLACK['g'],$this->BLACK['b']);
			$pdf->MultiCell(80, 4, $outputlangs->convToOutputCharset($this->emetteur->name), 0, 'L');

			// Show sender information
			$pdf->SetXY($posx+$retrait_cadre,$posy+10);
			$pdf->SetFont($ubuntu['light']['normal'],'', $default_font_size);
			$pdf->MultiCell(80, 4, $carac_emetteur, 0, 'L');


			// If CUSTOMER contact defined, we use it
			$usecontact=false;
			$arrayidcontact=$object->getIdContact('external','CUSTOMER');
			if (count($arrayidcontact) > 0)
			{
				$usecontact=true;
				$result=$object->fetch_contact($arrayidcontact[0]);
				$contact_client = $object->contact;
			}
			else {
				$contact_client = null;
			}

			// Recipient name
			if (! empty($usecontact))
			{
				// On peut utiliser le nom de la societe du contact
				if (! empty($conf->global->MAIN_USE_COMPANY_NAME_OF_CONTACT)) $socname = $object->contact->socname;
				else $socname = $object->client->nom;
				$carac_client_name=$outputlangs->convToOutputCharset($socname);
			}
			else
			{
				$carac_client_name=$outputlangs->convToOutputCharset($object->client->nom);
			}

			//$carac_client=pdf_build_address($outputlangs,$this->emetteur,$object->client,($usecontact?$object->contact:''),$usecontact,'target');
			$carac_client = $this->pdf_build_address($outputlangs, $object->client, $contact_client, 'external');

			// Show recipient
			$widthrecbox=100;
			if ($this->page_largeur < 210) $widthrecbox=84;	// To work with US executive format
			$posy=$address_boxes_posy;
			$posx=$this->page_largeur-$this->marge_droite-$widthrecbox;
			if (! empty($conf->global->MAIN_INVERT_SENDER_RECIPIENT)) $posx=$this->marge_gauche;

			// Show recipient frame
			$pdf->SetTextColor($this->CC_PINK['r'],$this->CC_PINK['g'],$this->CC_PINK['b']);
			$pdf->SetFont($ubuntu['regular']['normal'],'', $default_font_size);
			$pdf->SetXY($posx,$posy-6);
			$pdf->MultiCell($widthrecbox, 5, $outputlangs->transnoentities("BillTo"), 0, 'L');
			$pdf->Rect($posx, $posy, $widthrecbox, $hautcadre);

			// Show recipient name
			$pdf->SetTextColor($this->BLACK['r'],$this->BLACK['g'],$this->BLACK['b']);
			$pdf->SetXY($posx+$retrait_cadre,$posy+3);
			$pdf->SetFont($ubuntu['bold']['normal'],'B', $default_font_size);
			$pdf->MultiCell($widthrecbox, 4, $carac_client_name, 0, 'L');

			// Show recipient information
			$pdf->SetFont($ubuntu['light']['normal'],'', $default_font_size);
			$pdf->SetXY($posx+$retrait_cadre,$posy+6+(dol_nboflines_bis($carac_client_name,50)*4));
			$pdf->MultiCell($widthrecbox, 4, $carac_client, 0, 'L');
		}
	}
	
	/**
	 * Construire une adresse pour le header.
	 * 
	 * Cette méthode supplante la fonction Dolibarr du même nom étant donné que
	 * cette dernière ne permet pas d'avoir le rendu souhaité..
	 * @param type $outputlangs Gestionnaire de langues
	 * @param type $company L'entreprise de l'adresse
	 * @param mixed $contact Le contact
	 * @param type $source_contact Source du contact : 'internal' pour l'émetteur
	 * et 'external' pour le client.
	 * @return type L'adresse construite et prête à être insérée dans le PDF.
	 */
	function pdf_build_address($outputlangs, $company, $contact, $source_contact) {
		if (!is_object($outputlangs)) {
			global $langs;
			$outputlangs = $langs;
		}
		
		$adresse = array();
		
		// Adresse de la société
		$adresse[] = $outputlangs->convToOutputCharset(dol_format_address($company));
		
		// Coordonnées du contact
		if (is_object($contact)) {
			$adresse_contact = array();
			
			// Identité
			$identite = array();
			if (!empty($contact->civilite)) {
				$identite[] = $contact->getCivilityLabel();
			}
			if (!empty($contact->firstname)) {
				$identite[] = ucfirst($contact->firstname);
			}
			if (!empty($contact->lastname)) {
				$identite[] = ucfirst($contact->lastname);
			}
			$identite = implode(' ', $identite);
			if (!empty($identite)) {
				$adresse_contact[] = $identite;
			}
			
			// N°s de téléphone
			$tels = array();
			if ($source_contact == 'internal') {
				$pro = 'office_phone';
				$mobile = 'user_mobile';
			}
			else {
				$pro = 'phone_perso';
				$mobile = 'phone_mobile';
			}
			if (!empty($contact->$pro)) {
				$tels[] = $contact->$pro;
			}
			if (!empty($contact->$mobile)) {
				$tels[] = $contact->$mobile;
			}
			$tels = implode(' | ', $tels);
			if (!empty($tels)) {
				$adresse_contact[] = $tels;
			}
			
			// Courriel entreprise
			$adresse_contact[] = $company->email;
			
			// Ajout à l'adresse finale
			$adresse_contact = implode("\n", $adresse_contact);
			if (!empty($adresse_contact)) {
				$adresse[] = $adresse_contact;
			}
		}
		
		return implode("\n\n", $adresse);
	}

	/**
	 *   	Show footer of page. Need this->emetteur object
	 *
	 *   	@param	PDF			&$pdf     			PDF
	 * 		@param	Object		$object				Object to show
	 *      @param	Translate	$outputlangs		Object lang for output
	 *      @param	int			$hidefreetext		1=Hide free text
	 *      @return	int								Return height of bottom margin including footer text
	 */
	function _pagefoot(&$pdf,$object,$outputlangs,$hidefreetext=0)
	{
		return self::pdf_pagefoot_codecouleurs($pdf,$outputlangs,'PROPALE_FREE_TEXT',$this->emetteur,$this->marge_basse,$this->marge_gauche,$this->page_hauteur,$object,0,$hidefreetext);
	}

	/**
	 *  Show footer of page for PDF generation
	 *
	 *	@param	PDF			&$pdf     		The PDF factory
	 *  @param  Translate	$outputlangs	Object lang for output
	 * 	@param	string		$paramfreetext	Constant name of free text
	 * 	@param	Societe		$fromcompany	Object company
	 * 	@param	int			$marge_basse	Margin bottom we use for the autobreak
	 * 	@param	int			$marge_gauche	Margin left (no more used)
	 * 	@param	int			$page_hauteur	Page height (no more used)
	 * 	@param	Object		$object			Object shown in PDF
	 * 	@param	int			$showdetails	Show company details into footer. This param seems to not be used by standard version.
	 *  @param	int			$hidefreetext	1=Hide free text, 0=Show free text
	 * 	@return	int							Return height of bottom margin including footer text
	 */
	function pdf_pagefoot_codecouleurs(&$pdf,$outputlangs,$paramfreetext,$fromcompany,$marge_basse,$marge_gauche,$page_hauteur,$object,$showdetails=0,$hidefreetext=0)
	{
		global $conf,$user;

		$outputlangs->load("cc_extras");
		$outputlangs->load("dict");
		$line='';
		
		$ubuntu = $this->get_ubuntu_font_array($outputlangs);
		$default_font_size = pdf_getPDFFontSize($outputlangs) -2;
		$dims=$pdf->getPageDimensions();

		// Line of free text
		if (empty($hidefreetext) && ! empty($conf->global->$paramfreetext))
		{
			// Make substitution
			$substitutionarray=array(
				'__FROM_NAME__' => $fromcompany->nom,
				'__FROM_EMAIL__' => $fromcompany->email,
				'__TOTAL_TTC__' => $object->total_ttc,
				'__TOTAL_HT__' => $object->total_ht,
				'__TOTAL_VAT__' => $object->total_vat
			);
			complete_substitutions_array($substitutionarray,$outputlangs,$object);
			$newfreetext=make_substitutions($conf->global->$paramfreetext,$substitutionarray);
			$line.=$outputlangs->convToOutputCharset($newfreetext);
		}

		// First line of company infos

		if ($showdetails)
		{
			$line1="";
			// Company name
			if ($fromcompany->name)
			{
				$line1.=($line1?" - ":"").$outputlangs->transnoentities("RegisteredOffice").": ".$fromcompany->name;
			}
			// Address
			if ($fromcompany->address)
			{
				$line1.=($line1?" - ":"").$fromcompany->address;
			}
			// Zip code
			if ($fromcompany->zip)
			{
				$line1.=($line1?" - ":"").$fromcompany->zip;
			}
			// Town
			if ($fromcompany->town)
			{
				$line1.=($line1?" ":"").$fromcompany->town;
			}
			// Phone
			if ($fromcompany->phone)
			{
				$line1.=($line1?" - ":"").$outputlangs->transnoentities("Phone").": ".$fromcompany->phone;
			}
			// Fax
			if ($fromcompany->fax)
			{
				$line1.=($line1?" - ":"").$outputlangs->transnoentities("Fax").": ".$fromcompany->fax;
			}

			$line2="";
			// URL
			if ($fromcompany->url)
			{
				$line2.=($line2?" - ":"").$fromcompany->url;
			}
			// Email
			if ($fromcompany->email)
			{
				$line2.=($line2?" - ":"").$fromcompany->email;
			}
		}

		// Line 3 of company infos
		$line3= array();
		// Nom de l'entreprise
		if ($fromcompany->name)
		{
			$line3[] = $fromcompany->name;
		}
		// Juridical status
		if ($fromcompany->forme_juridique_code)
		{
			$line3[] = $outputlangs->convToOutputCharset(getFormeJuridiqueLabel($fromcompany->forme_juridique_code));
		}
		// Capital
		if ($fromcompany->capital)
		{
			$line3[] = $fromcompany->capital;
		}
		// Prof Id 1
		if ($fromcompany->idprof1 && ($fromcompany->country_code != 'FR' || ! $fromcompany->idprof2))
		{
			$field=$outputlangs->transcountrynoentities("CC_ProfId1",$fromcompany->country_code);
			$real_field = (preg_match('/\((.*)\)/i',$field,$reg)) ? $reg[1] : '';
			$line3[] = $real_field." : ".$outputlangs->convToOutputCharset($fromcompany->idprof1);
		}
		// Prof Id 2
		if ($fromcompany->idprof2)
		{
			$field=$outputlangs->transcountrynoentities("CC_ProfId2",$fromcompany->country_code);
			$real_field = (preg_match('/\((.*)\)/i',$field,$reg)) ? $reg[1] : '';
			$line3[] = $real_field." : ".$outputlangs->convToOutputCharset($fromcompany->idprof2);
		}
		$line3 = implode(' - ', $line3);

		// Line 4 of company infos
		$line4= array();
		// Prof Id 3
		if ($fromcompany->idprof3)
		{
			$field=$outputlangs->transcountrynoentities("CC_ProfId3",$fromcompany->country_code);
			$real_field = (preg_match('/\((.*)\)/i',$field,$reg)) ? $reg[1] : '';
			$line4[] = $real_field." : ".$outputlangs->convToOutputCharset($fromcompany->idprof3);
		}
		// Prof Id 4
		if ($fromcompany->idprof4)
		{
			$field=$outputlangs->transcountrynoentities("CC_ProfId4",$fromcompany->country_code);
			$real_field = (preg_match('/\((.*)\)/i',$field,$reg)) ? $reg[1] : '';
			$line4[] = $real_field." : ".$outputlangs->convToOutputCharset($fromcompany->idprof4);
		}
		// IntraCommunautary VAT
		if ($fromcompany->tva_intra != '')
		{
			$line4[] = $outputlangs->transnoentities("CC_VATIntraShort")." : ".$outputlangs->convToOutputCharset($fromcompany->tva_intra);
		}
		$line4 = implode(' - ', $line4);

		$pdf->SetFont($ubuntu['bold']['normal'],'', $default_font_size);
		$pdf->SetTextColor($this->CC_DARK_GREY['r'], $this->CC_DARK_GREY['g'], $this->CC_DARK_GREY['b']);
		$pdf->SetDrawColor($this->CC_DARK_GREY['r'], $this->CC_DARK_GREY['g'], $this->CC_DARK_GREY['b']);

		// On positionne le debut du bas de page selon nbre de lignes de ce bas de page
		$freetextheight=0;
		if ($line)	// Free text
		{
			$width=20000; $align='L';	// By default, ask a manual break: We use a large value 20000, to not have automatic wrap. This make user understand, he need to add CR on its text.
			if (! empty($conf->global->MAIN_USE_AUTOWRAP_ON_FREETEXT)) {
				$width=200; $align='C';
			}
			$freetextheight=$pdf->getStringHeight($width,$line);
		}

		$marginwithfooter=$marge_basse + $freetextheight + 3*((!empty($line1)) + (!empty($line2)) + (!empty($line3)) + (!empty($line4)));
		$posy=$marginwithfooter;

		if ($line)	// Free text
		{
			$pdf->SetXY($dims['lm'],-$posy);
			$pdf->MultiCell($width, 3, $line, 0, $align, 0);
			$posy-=$freetextheight;
		}
		
		$posy+=$this->ONE_MORE_LINE/2;

		$pdf->SetY(-$posy);
		$pdf->line($dims['lm'], $dims['hk']-$posy, $dims['wk']-$dims['rm'], $dims['hk']-$posy);
		$posy-= $this->ONE_MORE_LINE/2;
		
		if (!empty($line1))
		{
			$pdf->SetFont($ubuntu['bold']['normal'],'', $default_font_size);
			$pdf->SetXY($dims['lm'],-$posy);
			$pdf->MultiCell(200, 2, $line1, 0, 'C', 0);
			$posy -= $this->ONE_MORE_LINE;
			$pdf->SetFont($ubuntu['bold']['normal'],'', $default_font_size);
		}

		if (!empty($line2))
		{
			$pdf->SetFont($ubuntu['bold']['normal'],'', $default_font_size);
			$pdf->SetXY($dims['lm'],-$posy);
			$pdf->MultiCell(200, 2, $line2, 0, 'C', 0);
			$posy -= $this->ONE_MORE_LINE;
			$pdf->SetFont($ubuntu['bold']['normal'],'', $default_font_size);
		}

		if (!empty($line3))
		{
			$pdf->SetXY($dims['lm'],-$posy);
			$pdf->MultiCell(200, 2, $line3, 0, 'C', 0);
		}

		if (!empty($line4))
		{
			$posy -= $this->ONE_MORE_LINE;
			$pdf->SetXY($dims['lm'],-$posy);
			$pdf->MultiCell(200, 2, $line4, 0, 'C', 0);
		}

		// Show page number only if there are at least two pages.
		if ($pdf->getNumPages() > 1)
		{
			$pdf->SetXY(-20,-$posy);
			$pdf->MultiCell(11, 2, $pdf->PageNo().'/'.$pdf->getNumPages(), 0, 'R', 0);
		}

		return $marginwithfooter;
	}

	/**
	 * Array with Ubuntu fonts names
	 * @return array Array whose indexes are the different emphasis (light,
	 * medium, regular, bold) and whose values are arrays containing the normal
	 * and italic fonts of the emphasis.
	 */
	function get_ubuntu_font_array($outputlangs) {
		if (!is_object($outputlangs)){
			global $langs;
			$outputlangs=$langs;
		}

		$outputlangs->load("main");
		$ubuntu_font_family = pdf_getPDFFont($outputlangs);
		
		$ubuntu = array();
		foreach (array('light', 'medium', 'regular', 'bold') as $graisse) {
			!isset($ubuntu[$graisse]) AND ($ubuntu[$graisse] = array());
			
			$nom = array($ubuntu_font_family);
			($graisse != 'regular') AND ($nom[] = $graisse);
			
			foreach (array('normal', 'italic') as $graphie) {
				($graphie != 'normal') AND ($nom[] = $graphie);
				$ubuntu[$graisse][$graphie] = implode('_', $nom);
			}
		}
		
		return $ubuntu;
	}
}
