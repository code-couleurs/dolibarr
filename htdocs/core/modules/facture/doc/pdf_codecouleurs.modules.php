<?php
/* Copyright (C) 2004-2012	Laurent Destailleur	<eldy@users.sourceforge.net>
 * Copyright (C) 2005-2012	Regis Houssin		<regis@dolibarr.fr>
 * Copyright (C) 2008		Raphael Bertrand	<raphael.bertrand@resultic.fr>
 * Copyright (C) 2010-2012	Juanjo Menent		<jmenent@2byte.es>
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
 *	\file       htdocs/core/modules/facture/doc/pdf_crabe.modules.php
 *	\ingroup    facture
 *	\brief      File of class to generate customers invoices from crabe model
 */

require_once DOL_DOCUMENT_ROOT.'/core/modules/facture/modules_facture.php';
require_once DOL_DOCUMENT_ROOT.'/product/class/product.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/company.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/functions2.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/pdf.lib.php';


/**
 *	Class to manage PDF invoice template Crabe
 */
class pdf_codecouleurs extends ModelePDFFactures
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
		$this->description = $langs->trans('PDFCrabeDescription');

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

		$this->option_logo = 1;                    // Affiche logo
		$this->option_tva = 1;                     // Gere option tva FACTURE_TVAOPTION
		$this->option_modereg = 1;                 // Affiche mode reglement
		$this->option_condreg = 1;                 // Affiche conditions reglement
		$this->option_codeproduitservice = 1;      // Affiche code produit-service
		$this->option_multilang = 1;               // Dispo en plusieurs langues
		$this->option_escompte = 1;                // Affiche si il y a eu escompte
		$this->option_credit_note = 1;             // Support credit notes
		$this->option_freetext = 1;				   // Support add of a personalised text
		$this->option_draft_watermark = 1;		   // Support add of a watermark on drafts

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
	 *  @return     int         	    			1=OK, 0=KO
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
		$outputlangs->load("products");

		if ($conf->facture->dir_output)
		{
			$object->fetch_thirdparty();

			$deja_regle = $object->getSommePaiement();
			$amount_credit_notes_included = $object->getSumCreditNotesUsed();
			$amount_deposits_included = $object->getSumDepositsUsed();

			// Definition of $dir and $file
			if ($object->specimen)
			{
				$dir = $conf->facture->dir_output;
				$file = $dir . "/SPECIMEN.pdf";
			}
			else
			{
				$objectref = dol_sanitizeFileName($object->ref);
				$dir = $conf->facture->dir_output . "/" . $objectref;
				$file = $dir . "/" . $objectref . ".pdf";
			}
			if (! file_exists($dir))
			{
				if (dol_mkdir($dir) < 0)
				{
					$this->error=$langs->transnoentities("ErrorCanNotCreateDir",$dir);
					return 0;
				}
			}

			if (file_exists($dir))
			{
				$nblignes = count($object->lines);

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
				$line_style = array(
					'width' => 0.15,
					'dash'  => '0.05,1.4',
					'color' => array($this->CC_DARK_GREY['r'], $this->CC_DARK_GREY['g'], $this->CC_DARK_GREY['b']),
					'cap'   => 'round',
					'join'  => 'round'
				);
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
				$pdf->SetSubject($outputlangs->transnoentities("Invoice"));
				$pdf->SetCreator("Dolibarr ".DOL_VERSION);
				$pdf->SetAuthor($outputlangs->convToOutputCharset($user->getFullName($outputlangs)));
				$pdf->SetKeyWords($outputlangs->convToOutputCharset($object->ref)." ".$outputlangs->transnoentities("Invoice"));
				if (! empty($conf->global->MAIN_DISABLE_PDF_COMPRESSION)) $pdf->SetCompression(false);

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

				$tab_top = 100;
				$tab_top_newpage = (empty($conf->global->MAIN_PDF_DONOTREPEAT_HEAD)?42:10);
				$tab_height = 130;

				// Affiche notes
				if (! empty($object->note_public))
				{
					$pdf->SetFont($ubuntu['light']['normal'],'', $default_font_size - 1);
					$pdf->writeHTMLCell(190, 3, $this->posxdesc-1, $tab_top, dol_htmlentitiesbr($object->note_public), 0, 1);
					$nexY = $pdf->GetY();
					$height_note=$nexY-$tab_top;

					$tab_height = $tab_height - $height_note;
					$tab_top = $nexY + 9 + $height_note;
				}
				else
				{
					$height_note=0;
				}

				$iniY = $tab_top + 7;
				$curY = $tab_top + 7;
				$nexY = $tab_top + 7;
				$tab_header_height = 6;

				// Loop on each lines
				for ($i = 0; $i < $nblignes; $i++)
				{
					$curY = $nexY;
					$pdf->SetFont($ubuntu['light']['normal'],'', $default_font_size - 1);   // Into loop to work with multipage
					$pdf->SetTextColor($this->BLACK['r'],$this->BLACK['g'],$this->BLACK['b']);

					// On rajoute $tab_header_height à la topMargin pour éviter d'écrire quoi que ce soit dans le header récapitulatif.
					$pdf->setTopMargin($tab_top_newpage + $tab_height);
					$pdf->setPageOrientation('', 1, $heightforfooter);	// The only function to edit the bottom margin of current page to set it.
					$pageposbefore=$pdf->getPage();

					// Description of product line
					$curX = $this->posxdesc-1;

					$showpricebeforepagebreak=1;

					$pdf->startTransaction();

					//$qty = pdf_getlineqty($object, $i, $outputlangs, $hidedetails, $hookmanager);
					$qty = intval($object->lines[$i]->qty);

					// Add line
					if(!preg_match("/".preg_quote("-", '/')."/A", $object->lines[$i]->desc)) {
						$bold_line = ($qty == 0);
						$pdf->SetLineStyle($line_style);
						$pdf->line($this->marge_gauche, $curY -1, $this->page_largeur - $this->marge_droite, $curY-1);
						$pdf->SetLineStyle(array('dash'=>0));
					}
					else {
						$bold_line = false;
					}
					$this->pdf_writelinedesc($pdf, $object, $i, $this->posxtva-$curX, $curY, $outputlangs, $bold_line);
					//pdf_writelinedesc($pdf,$object,$i,$outputlangs,$this->posxtva-$curX,3,$curX,$curY,$hideref,$hidedesc,0,$hookmanager);
					
					$pdf->SetFont($ubuntu['light']['normal'],'', $default_font_size - 1);
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
						
						// Add line
						if(!preg_match("/".preg_quote("-", '/')."/A", $object->lines[$i]->desc)) {
							$bold_line = ($qty == 0);
							$pdf->SetLineStyle($line_style);
							$pdf->line($this->marge_gauche, $curY -1, $this->page_largeur - $this->marge_droite, $curY-1);
							$pdf->SetLineStyle(array('dash'=>0));
						}
						else {
							$bold_line = false;
						}
						$this->pdf_writelinedesc($pdf, $object, $i, $this->posxtva-$curX, $curY, $outputlangs, $bold_line);
						//pdf_writelinedesc($pdf,$object,$i,$outputlangs,$this->posxtva-$curX,4,$curX,$curY,$hideref,$hidedesc,0,$hookmanager);
						
						$pdf->SetFont($ubuntu['light']['normal'],'', $default_font_size - 1);
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
					if($qty > 0) {
						// Unit price before discount
						if (empty($conf->global->MAIN_GENERATE_DOCUMENTS_WITHOUT_VAT))
						{
							$pdf->SetXY($this->posxtva, $curY);
							$up_excl_tax = pdf_getlineupexcltax($object, $i, $outputlangs, $hidedetails, $hookmanager);
							$pdf->MultiCell($this->posxup-$this->posxtva-1, 3, $up_excl_tax, 0, 'R');
						}

						// Discount on line
						if ($object->lines[$i]->remise_percent)
						{
							$pdf->SetXY($this->posxup, $curY);
							$remise_percent = pdf_getlineremisepercent($object, $i, $outputlangs, $hidedetails, $hookmanager);
							$pdf->MultiCell($this->posxqty-$this->posxup-1, 3, $remise_percent, 0, 'R', 0);
						}

						// VAT Rate
						$vat_rate = pdf_getlinevatrate($object, $i, $outputlangs, $hidedetails, $hookmanager);
						$pdf->SetXY($this->posxqty, $curY);
						$pdf->MultiCell($this->posxdiscount-$this->posxqty-1, 3, $vat_rate, 0, 'R');	// Enough for 6 chars

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
					}
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
					if (! isset($this->tva[$vatrate])) 				$this->tva[$vatrate]='';
					if (! isset($this->localtax1[$localtax1rate])) 	$this->localtax1[$localtax1rate]='';
					if (! isset($this->localtax2[$localtax2rate])) 	$this->localtax2[$localtax2rate]='';
					$this->tva[$vatrate] += $tvaligne;
					$this->localtax1[$localtax1rate]+=$localtax1ligne;
					$this->localtax2[$localtax2rate]+=$localtax2ligne;

					// We suppose that a too long description is moved completely on next page
					if ($pageposafter > $pageposbefore) {
						$pdf->setPage($pageposafter);
						$curY = $tab_top_newpage + $tab_header_height;
					}

					$nexY+=2;    // Passe espace entre les lignes

					$current_tab_height += ($nexY - $curY);
					// Detect if some page were added automatically and output _tableau for past pages
					while ($pagenb < $pageposafter)
					{
						$pdf->setPage($pagenb);
						if ($pagenb == 1)
						{
							$this->_tableau($pdf, $tab_top, $this->page_hauteur - $tab_top - $heightforfooter, 0, $outputlangs, 0, 1);
						}
						else
						{
							$this->_tableau($pdf, $tab_top_newpage, $this->page_hauteur - $tab_top_newpage - $heightforfooter, 0, $outputlangs, 1, 1);
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
							$this->_tableau($pdf, $tab_top, $this->page_hauteur - $tab_top - $heightforfooter, 0, $outputlangs, 0, 1);
						}
						else
						{
							$this->_tableau($pdf, $tab_top_newpage, $this->page_hauteur - $tab_top_newpage - $heightforfooter, 0, $outputlangs, 1, 1);
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
				
				$ecrire_versements = ($deja_regle OR $amount_credit_notes_included OR $amount_deposits_included);
				
				$pdf->startTransaction();
				$posy = $this->_notes_post_tableau($pdf, $object, $bottomlasttab, $outputlangs, $ecrire_versements);
				
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
					$posy = $this->_notes_post_tableau($pdf, $object, $this->FIRST_AFTER_HEADER, $outputlangs, $ecrire_versements);
				}
				else {
					$pdf->commitTransaction();
				}

				// Pied de page
				for ($page = 1; $page <= $pdf->getNumPages(); $page++) {
					$pdf->setPage($page);
					$this->_pagefoot($pdf,$object,$outputlangs);
				}

				$pdf->Close();

				$pdf->Output($file,'F');

				// Add pdfgeneration hook
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
			$this->error=$langs->trans("ErrorConstantNotDefined","FAC_OUTPUTDIR");
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
	function pdf_writelinedesc(&$pdf, $object, $i, $w, $posy, $outputlangs, $bold_line=false) {
		global $langs;

		if (!is_object($outputlangs)) {
			$outputlangs = $langs;
		}
		
		$ubuntu = $this->get_ubuntu_font_array($outputlangs);
		$default_size = pdf_getPDFFontSize($outputlangs);
		
		$description = trim($object->lines[$i]->desc);
		$retrait = 4;
		
		// Extraire la première ligne
		$ligne1 = trim(html_entity_decode(strtok(htmlentities($description), "\n")));
		
		// Écrire la première ligne
		$graisse = $bold_line ? 'medium' : 'regular';
		$pdf->SetFont($ubuntu[$graisse]['normal'], '', $default_size - 1);
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
	 *  Show payments table
	 *
	 *  @param	PDF			&$pdf           Object PDF
	 *  @param  Object		$object         Object invoice
	 *  @param  int			$posy           Position y in PDF
	 *  @param  Translate	$outputlangs    Object langs for output
	 *  @return int             			<0 if KO, >0 if OK
	 */
	function _tableau_versements(&$pdf, $object, $posy, $outputlangs)
	{
		$tab3_posx = 120;
		$tab3_top = $posy + 8;
		$tab3_width = 80;
		$tab3_height = 4;
		if ($this->page_largeur < 210) // To work with US executive format
		{
			$tab3_posx -= 20;
		}

		$ubuntu = $this->get_ubuntu_font_array($outputlangs);
		$default_font_size = pdf_getPDFFontSize($outputlangs);

		$pdf->SetFont($ubuntu['light']['normal'],'', $default_font_size - 3);
		$pdf->SetXY($tab3_posx, $tab3_top - 4);
		$pdf->MultiCell(60, 3, $outputlangs->transnoentities("PaymentsAlreadyDone"), 0, 'L', 0);

		$pdf->line($tab3_posx, $tab3_top, $tab3_posx+$tab3_width, $tab3_top);

		$pdf->SetFont($ubuntu['light']['normal'],'', $default_font_size - 4);
		$pdf->SetXY($tab3_posx, $tab3_top);
		$pdf->MultiCell(20, 3, $outputlangs->transnoentities("Payment"), 0, 'L', 0);
		$pdf->SetXY($tab3_posx+21, $tab3_top);
		$pdf->MultiCell(20, 3, $outputlangs->transnoentities("Amount"), 0, 'L', 0);
		$pdf->SetXY($tab3_posx+40, $tab3_top);
		$pdf->MultiCell(20, 3, $outputlangs->transnoentities("Type"), 0, 'L', 0);
		$pdf->SetXY($tab3_posx+58, $tab3_top);
		$pdf->MultiCell(20, 3, $outputlangs->transnoentities("Num"), 0, 'L', 0);

		$pdf->line($tab3_posx, $tab3_top-1+$tab3_height, $tab3_posx+$tab3_width, $tab3_top-1+$tab3_height);

		$y=0;

		$pdf->SetFont($ubuntu['light']['normal'],'', $default_font_size - 4);

		// Loop on each deposits and credit notes included
		$sql = "SELECT re.rowid, re.amount_ht, re.amount_tva, re.amount_ttc,";
		$sql.= " re.description, re.fk_facture_source,";
		$sql.= " f.type, f.datef";
		$sql.= " FROM ".MAIN_DB_PREFIX ."societe_remise_except as re, ".MAIN_DB_PREFIX ."facture as f";
		$sql.= " WHERE re.fk_facture_source = f.rowid AND re.fk_facture = ".$object->id;
		$resql=$this->db->query($sql);
		if ($resql)
		{
			$num = $this->db->num_rows($resql);
			$i=0;
			$invoice=new Facture($this->db);
			while ($i < $num)
			{
				$y+=3;
				$obj = $this->db->fetch_object($resql);

				if ($obj->type == 2) $text=$outputlangs->trans("CreditNote");
				elseif ($obj->type == 3) $text=$outputlangs->trans("Deposit");
				else $text=$outputlangs->trans("UnknownType");

				$invoice->fetch($obj->fk_facture_source);

				$pdf->SetXY($tab3_posx, $tab3_top+$y);
				$pdf->MultiCell(20, 3, dol_print_date($obj->datef,'day',false,$outputlangs,true), 0, 'L', 0);
				$pdf->SetXY($tab3_posx+21, $tab3_top+$y);
				$pdf->MultiCell(20, 3, price($obj->amount_ttc), 0, 'L', 0);
				$pdf->SetXY($tab3_posx+40, $tab3_top+$y);
				$pdf->MultiCell(20, 3, $text, 0, 'L', 0);
				$pdf->SetXY($tab3_posx+58, $tab3_top+$y);
				$pdf->MultiCell(20, 3, $invoice->ref, 0, 'L', 0);

				$pdf->line($tab3_posx, $tab3_top+$y+3, $tab3_posx+$tab3_width, $tab3_top+$y+3);

				$i++;
			}
		}
		else
		{
			$this->error=$this->db->lasterror();
			dol_syslog($this->db,$this->error, LOG_ERR);
			return -1;
		}

		// Loop on each payment
		$sql = "SELECT p.datep as date, p.fk_paiement as type, p.num_paiement as num, pf.amount as amount,";
		$sql.= " cp.code";
		$sql.= " FROM ".MAIN_DB_PREFIX."paiement_facture as pf, ".MAIN_DB_PREFIX."paiement as p";
		$sql.= " LEFT JOIN ".MAIN_DB_PREFIX."c_paiement as cp ON p.fk_paiement = cp.id";
		$sql.= " WHERE pf.fk_paiement = p.rowid AND pf.fk_facture = ".$object->id;
		$sql.= " ORDER BY p.datep";
		$resql=$this->db->query($sql);
		if ($resql)
		{
			$num = $this->db->num_rows($resql);
			$i=0;
			while ($i < $num) {
				$y+=3;
				$row = $this->db->fetch_object($resql);

				$pdf->SetXY($tab3_posx, $tab3_top+$y);
				$pdf->MultiCell(20, 3, dol_print_date($this->db->jdate($row->date),'day',false,$outputlangs,true), 0, 'L', 0);
				$pdf->SetXY($tab3_posx+21, $tab3_top+$y);
				$pdf->MultiCell(20, 3, price($row->amount), 0, 'L', 0);
				$pdf->SetXY($tab3_posx+40, $tab3_top+$y);
				$oper = $outputlangs->transnoentitiesnoconv("PaymentTypeShort" . $row->code);

				$pdf->MultiCell(20, 3, $oper, 0, 'L', 0);
				$pdf->SetXY($tab3_posx+58, $tab3_top+$y);
				$pdf->MultiCell(30, 3, $row->num, 0, 'L', 0);

				$pdf->line($tab3_posx, $tab3_top+$y+3, $tab3_posx+$tab3_width, $tab3_top+$y+3);

				$i++;
			}
			
			return $pdf->GetY();
		}
		else
		{
			$this->error=$this->db->lasterror();
			dol_syslog($this->db,$this->error, LOG_ERR);
			return -1;
		}

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
	function _notes_post_tableau(&$pdf, $object, $posy, $outputlangs, $ecrire_versements) {
		// Affiche zone infos
		$posy_gauche=$this->_tableau_info($pdf, $object, $posy, $outputlangs);
		//$posy_gauche=$this->_affichage_notes_et_mentions($pdf, $object, $posy_gauche, $outputlangs);

		// Affiche zone totaux
		$posy_droit=$this->_tableau_tot($pdf, $object, 0, $posy, $outputlangs);
		if ($ecrire_versements) {
			$posy_droit = $this->_tableau_versements($pdf, $object, $posy_droit, $outputlangs);
		}
		
		return max($posy_gauche, $posy_droit);
	}
	
//	/**
//	 * Affichage des notes publiques et des mentions liées aux clients
//	 *   @param		PDF			&$pdf     		Object PDF
//	 *   @param		Object		$object			Object to show
//	 *   @param		int			$posy			Y
//	 *   @param		Translate	$outputlangs	Langs object
//	 *   @return	int			New Y
//	 */
//	function _affichage_notes_et_mentions(&$pdf, $object, $posy, $outputlangs) {
//		// Le contenu est affiché dans les notes publiques
//		
//		// Pas de notes : on ne fait rien.
//		if ($object->note_public === '') {
//			return $posy;
//		}
//		
//		$col_width = 110 - $this->marge_gauche;
//		
//		$ubuntu = $this->get_ubuntu_font_array($outputlangs);
//		$default_font_size = pdf_getPDFFontSize($outputlangs);
//
//		$pdf->SetFont($ubuntu['light']['normal'],'', $default_font_size - 2);
//		$pdf->SetTextColor($this->BLACK['r'],$this->BLACK['g'],$this->BLACK['b']);
//		$pdf->writeHTMLCell(
//			$col_width, $this->ONE_MORE_LINE, $this->posxdesc-1, $posy, dol_htmlentitiesbr($object->note_public),
//			0, 1);
//		$nexY = $pdf->GetY();
//		$height_note=$nexY-$posy;
//		$posy = $nexY+9 + $height_note;
//		return $posy;
//	}


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

		// If France, show VAT mention if not applicable
		if ($this->emetteur->pays_code == 'FR' && $this->franchise == 1)
		{
			$pdf->SetFont($ubuntu['bold']['normal'],'B', $default_font_size - 2);
			$pdf->SetXY($this->marge_gauche, $posy);
			$pdf->MultiCell(100, 3, $outputlangs->transnoentities("VATIsNotUsedForInvoice"), 0, 'L', 0);

			$posy=$pdf->GetY()+4;
		}

		if ($object->client->country_code == 'CH')
		{
			$pdf->SetFont($ubuntu['light']['normal'],'', $default_font_size - 4);
			$pdf->SetXY($this->marge_gauche, $posy);
			$pdf->MultiCell(100, 3, 'Exonération de TVA en application de l\'article 2062 I du code général des impôts', 0, 'L', 0);

			$posy=$pdf->GetY()+4;
		}
        
        if (!empty($object->client->tva_intra) && !$object->client->tva_assuj)
		{
			$pdf->SetFont($ubuntu['light']['normal'],'', $default_font_size - 4);
			$pdf->SetXY($this->marge_gauche, $posy);
			$pdf->MultiCell(100, 3, 'TVA dûe par le preneur, article 283-2 du Code général des impôts', 0, 'L', 0);

			$posy=$pdf->GetY()+4;
		}

		$posxval=54;

		// Show payments conditions
		if ($object->type != 2 && ($object->cond_reglement_code || $object->cond_reglement))
		{
			$pdf->SetFont($ubuntu['bold']['normal'],'B', $default_font_size - 2);
			$pdf->SetXY($this->marge_gauche, $posy);
			$titre = $outputlangs->transnoentities("PaymentConditions").':';
			$pdf->MultiCell(80, 4, $titre, 0, 'L');

			$pdf->SetFont($ubuntu['light']['normal'],'', $default_font_size - 2);
			$pdf->SetXY($posxval, $posy);
			$lib_condition_paiement=$outputlangs->transnoentities("PaymentCondition".$object->cond_reglement_code)!=('PaymentCondition'.$object->cond_reglement_code)?$outputlangs->transnoentities("PaymentCondition".$object->cond_reglement_code):$outputlangs->convToOutputCharset($object->cond_reglement_doc);
			$lib_condition_paiement=str_replace('\n',"\n",$lib_condition_paiement);
			$pdf->MultiCell(80, 4, $lib_condition_paiement,0,'L');

			$posy=$pdf->GetY()+3;
		}

		if ($object->type != 2)
		{
			// Check a payment mode is defined
			if (empty($object->mode_reglement_code)
				&& ! $conf->global->FACTURE_CHQ_NUMBER
				&& ! $conf->global->FACTURE_RIB_NUMBER)
			{
				$pdf->SetXY($this->marge_gauche, $posy);
				$pdf->SetTextColor($this->CC_RED['r'],$this->CC_RED['g'],$this->CC_RED['b']);
				$pdf->SetFont('','B', $default_font_size - 2);
				$pdf->MultiCell(80, 3, $outputlangs->transnoentities("ErrorNoPaiementModeConfigured"),0,'L',0);
				$pdf->SetTextColor($this->BLACK['r'],$this->BLACK['g'],$this->BLACK['b']);

				$posy=$pdf->GetY()+1;
			}

			// Show payment mode
			if ($object->mode_reglement_code
				&& $object->mode_reglement_code != 'CHQ'
				&& $object->mode_reglement_code != 'VIR')
			{
				$pdf->SetFont($ubuntu['bold']['normal'],'B', $default_font_size - 2);
				$pdf->SetXY($this->marge_gauche, $posy);
				$titre = $outputlangs->transnoentities("PaymentMode").':';
				$pdf->MultiCell(80, 5, $titre, 0, 'L');

				$pdf->SetFont($ubuntu['light']['normal'],'', $default_font_size - 2);
				$pdf->SetXY($posxval, $posy);
				$lib_mode_reg=$outputlangs->transnoentities("PaymentType".$object->mode_reglement_code)!=('PaymentType'.$object->mode_reglement_code)?$outputlangs->transnoentities("PaymentType".$object->mode_reglement_code):$outputlangs->convToOutputCharset($object->mode_reglement);
				$pdf->MultiCell(80, 5, $lib_mode_reg,0,'L');

				$posy=$pdf->GetY()+2;
			}

			// Show payment mode CHQ
			if (empty($object->mode_reglement_code) || $object->mode_reglement_code == 'CHQ')
			{
				// Si mode reglement non force ou si force a CHQ
				if (! empty($conf->global->FACTURE_CHQ_NUMBER))
				{
					if ($conf->global->FACTURE_CHQ_NUMBER > 0)
					{
						$account = new Account($this->db);
						$account->fetch($conf->global->FACTURE_CHQ_NUMBER);

						$pdf->SetXY($this->marge_gauche, $posy);
						$pdf->SetFont($ubuntu['bold']['normal'],'B', $default_font_size - 3);
						$pdf->MultiCell(100, 3, $outputlangs->transnoentities('PaymentByChequeOrderedTo',$account->proprio),0,'L',0);
						$posy=$pdf->GetY()+1;

						if (empty($conf->global->MAIN_PDF_HIDE_CHQ_ADDRESS))
						{
							$pdf->SetXY($this->marge_gauche, $posy);
							$pdf->SetFont($ubuntu['light']['normal'],'', $default_font_size - 3);
							$pdf->MultiCell(100, 3, $outputlangs->convToOutputCharset($account->adresse_proprio), 0, 'L', 0);
							$posy=$pdf->GetY()+2;
						}
					}
					if ($conf->global->FACTURE_CHQ_NUMBER == -1)
					{
						$pdf->SetXY($this->marge_gauche, $posy);
						$pdf->SetFont($ubuntu['bold']['normal'],'B', $default_font_size - 3);
						$pdf->MultiCell(100, 3, $outputlangs->transnoentities('PaymentByChequeOrderedTo',$this->emetteur->name),0,'L',0);
						$posy=$pdf->GetY()+1;

						if (empty($conf->global->MAIN_PDF_HIDE_CHQ_ADDRESS))
						{
							$pdf->SetXY($this->marge_gauche, $posy);
							$pdf->SetFont($ubuntu['light']['normal'],'', $default_font_size - 3);
							$pdf->MultiCell(100, 3, $outputlangs->convToOutputCharset($this->emetteur->getFullAddress()), 0, 'L', 0);
							$posy=$pdf->GetY()+2;
						}
					}
				}
			}

			// If payment mode not forced or forced to VIR, show payment with BAN
			if (empty($object->mode_reglement_code) || $object->mode_reglement_code == 'VIR')
			{
				if (! empty($conf->global->FACTURE_RIB_NUMBER))
				{
					$account = new Account($this->db);
					$account->fetch($conf->global->FACTURE_RIB_NUMBER);

					$curx=$this->marge_gauche;
					$cury=$posy;

					$posy=pdf_bank($pdf,$outputlangs,$curx,$cury,$account,0,$default_font_size);

					$posy+=2;
				}
			}
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
	function _tableau_tot(&$pdf, $object, $deja_regle, $posy, $outputlangs)
	{
		global $conf,$mysoc;

		$sign=1;
		if ($object->type == 2 && ! empty($conf->global->INVOICE_POSITIVE_CREDIT_NOTE)) $sign=-1;

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
		$pdf->SetTextColor($this->BLACK['r'], $this->BLACK['g'], $this->BLACK['b']);
		$pdf->SetXY($col1x, $cur_posy);
		$pdf->MultiCell($col2x-$col1x, $tab2_hl, '', 0, 'L', 1);
		$pdf->SetXY($col1x, $cur_posy + $marguin);
		$pdf->MultiCell($col2x-$col1x, $text_height, $outputlangs->transnoentities("TotalHT"), 0, 'L', 1);
		$pdf->SetXY($col2x, $cur_posy);
		$pdf->MultiCell($largcol2, $tab2_hl, '', 0, 'R', 1);
		$pdf->SetXY($col2x, $cur_posy + $marguin);
		$pdf->MultiCell($largcol2, $text_height, price($sign * ($object->total_ht + (!empty($object->remise)) * $object->remise)), 0, 'R', 1);

		// Show VAT by rates and total
		$pdf->SetFillColor($this->WHITE['r'], $this->WHITE['g'], $this->WHITE['b']);

		$this->atleastoneratenotnull=0;
		if (empty($conf->global->MAIN_GENERATE_DOCUMENTS_WITHOUT_VAT))
		{
			$tvaisnull=((! empty($this->tva) && count($this->tva) == 1 && isset($this->tva['0.000']) && is_float($this->tva['0.000'])) ? true : false);
			if (! empty($conf->global->MAIN_GENERATE_DOCUMENTS_WITHOUT_VAT_ISNULL) && $tvaisnull)
			{
				// Nothing to do
			}
			else
			{
				foreach($this->tva as $tvakey => $tvaval)
				{
					if ($tvakey > 0)    // On affiche pas taux 0
					{
						$this->atleastoneratenotnull++;

						$cur_posy += $tab2_hl;
						$pdf->SetXY($col1x, $cur_posy + $marguin);
						$tvacompl='';
						if (preg_match('/\*/',$tvakey))
						{
							$tvakey=str_replace('*','',$tvakey);
							$tvacompl = " (".$outputlangs->transnoentities("NonPercuRecuperable").")";
						}
						$totalvat =$outputlangs->transnoentities("TotalVAT").' ';
						$totalvat.=vatrate($tvakey,1).$tvacompl;
						$pdf->MultiCell($col2x-$col1x, $text_height, $totalvat, 0, 'L', 1);
						$pdf->SetXY($col2x, $cur_posy + $marguin);
						$pdf->MultiCell($largcol2, $text_height, price($sign * $tvaval), 0, 'R', 1);
					}
				}

				if (! $this->atleastoneratenotnull)	// If no vat at all
				{
					$cur_posy += $tab2_hl;
					$pdf->SetXY($col1x, $cur_posy + $marguin);
					$pdf->MultiCell($col2x-$col1x, $tab2_hl, $outputlangs->transnoentities("TotalVAT"), 0, 'L', 1);
					$pdf->SetXY($col2x, $cur_posy + $marguin);
					$pdf->MultiCell($largcol2, $tab2_hl, price($sign * $object->total_tva), 0, 'R', 1);

					// Total LocalTax1
					if (! empty($conf->global->FACTURE_LOCAL_TAX1_OPTION) && $conf->global->FACTURE_LOCAL_TAX1_OPTION=='localtax1on' && $object->total_localtax1>0)
					{
						$cur_posy += $tab2_hl;
						$pdf->SetXY($col1x, $cur_posy + $marguin);
						$pdf->MultiCell($col2x-$col1x, $text_height, $outputlangs->transnoentities("TotalLT1".$mysoc->country_code), $useborder, 'L', 1);
						$pdf->SetXY($col2x, $cur_posy + $marguin);
						$pdf->MultiCell($largcol2, $text_height, price($sign * $object->total_localtax1), $useborder, 'R', 1);
					}

					// Total LocalTax2
					if (! empty($conf->global->FACTURE_LOCAL_TAX2_OPTION) && $conf->global->FACTURE_LOCAL_TAX2_OPTION=='localtax2on' && $object->total_localtax2>0)
					{
						$cur_posy += $tab2_hl;
						$pdf->SetXY($col1x, $cur_posy + $marguin);
						$pdf->MultiCell($col2x-$col1x, $text_height, $outputlangs->transnoentities("TotalLT2".$mysoc->country_code), $useborder, 'L', 1);
						$pdf->SetXY($col2x, $cur_posy + $marguin);
						$pdf->MultiCell($largcol2, $text_height, price($sign * $object->total_localtax2), $useborder, 'R', 1);
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
								//$this->atleastoneratenotnull++;

								$cur_posy += $tab2_hl;
								$pdf->SetXY($col1x, $cur_posy + $marguin);

								$tvacompl='';
								if (preg_match('/\*/',$tvakey))
								{
									$tvakey=str_replace('*','',$tvakey);
									$tvacompl = " (".$outputlangs->transnoentities("NonPercuRecuperable").")";
								}
								$totalvat =$outputlangs->transnoentities("TotalLT1".$mysoc->country_code).' ';
								$totalvat.=vatrate($tvakey,1).$tvacompl;
								$pdf->MultiCell($col2x-$col1x, $text_height, $totalvat, 0, 'L', 1);

								$pdf->SetXY($col2x, $cur_posy + $marguin);
								$pdf->MultiCell($largcol2, $text_height, price($sign * $tvaval), 0, 'R', 1);
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
								//$this->atleastoneratenotnull++;

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
								$pdf->MultiCell($col2x-$col1x, $text_height, $totalvat, 0, 'L', 1);

								$pdf->SetXY($col2x, $cur_posy + $marguin);
								$pdf->MultiCell($largcol2, $text_height, price($sign * $tvaval), 0, 'R', 1);
							}
						}
					}
				}

				// Total TTC
				$cur_posy += $tab2_hl;
				$pdf->SetTextColor($this->BLACK['r'], $this->BLACK['g'], $this->BLACK['b']);
				$pdf->SetFillColor($this->CC_LIGHT_GREY['r'], $this->CC_LIGHT_GREY['g'], $this->CC_LIGHT_GREY['b']);
				$pdf->SetXY($col1x, $cur_posy);
				$pdf->MultiCell($col2x-$col1x, $tab2_hl, '', $useborder, 'L', 1);
				$text=$outputlangs->transnoentities("TotalTTC");
				if ($object->type == 2) $text=$outputlangs->transnoentities("TotalTTCToYourCredit");
				$pdf->SetXY($col1x, $cur_posy + $marguin);
				$pdf->MultiCell($col2x-$col1x, $text_height, $text, $useborder, 'L', 1);
				$pdf->SetXY($col2x, $cur_posy);
				$pdf->MultiCell($largcol2, $tab2_hl, '', $useborder, 'R', 1);
				$pdf->SetXY($col2x, $cur_posy + $marguin);
				$pdf->MultiCell($largcol2, $text_height, price($sign * $object->total_ttc), $useborder, 'R', 1);
			}
		}

		$pdf->SetTextColor($this->BLACK['r'], $this->BLACK['g'], $this->BLACK['b']);

		$creditnoteamount=$object->getSumCreditNotesUsed();
		$depositsamount=$object->getSumDepositsUsed();
		$resteapayer = price2num($object->total_ttc - $deja_regle - $creditnoteamount - $depositsamount, 'MT');
		if ($object->paye) $resteapayer=0;

		if ($deja_regle > 0 || $creditnoteamount > 0 || $depositsamount > 0)
		{
			// Already paid + Deposits
			$cur_posy += $tab2_hl;
			$pdf->SetXY($col1x, $cur_posy + $marguin);
			$pdf->MultiCell($col2x-$col1x, $text_height, $outputlangs->transnoentities("Paid"), 0, 'L', 0);
			$pdf->SetXY($col2x, $cur_posy + $marguin);
			$pdf->MultiCell($largcol2, $text_height, price($deja_regle + $depositsamount), 0, 'R', 0);

			// Credit note
			if ($creditnoteamount)
			{
				$cur_posy += $tab2_hl;
				$pdf->SetXY($col1x, $cur_posy + $marguin);
				$pdf->MultiCell($col2x-$col1x, $text_height, $outputlangs->transnoentities("CreditNotes"), 0, 'L', 0);
				$pdf->SetXY($col2x, $cur_posy + $marguin);
				$pdf->MultiCell($largcol2, $text_height, price($creditnoteamount), 0, 'R', 0);
			}

			// Escompte
			if ($object->close_code == 'discount_vat')
			{
				$cur_posy += $tab2_hl;
				$pdf->SetFillColor($this->CC_LIGHT_GREY['r'], $this->CC_LIGHT_GREY['g'], $this->CC_LIGHT_GREY['b']);

				$pdf->SetXY($col1x, $cur_posy + $marguin);
				$pdf->MultiCell($col2x-$col1x, $tab2_hl, $outputlangs->transnoentities("EscompteOffered"), $useborder, 'L', 1);
				$pdf->SetXY($col2x, $cur_posy + $marguin);
				$pdf->MultiCell($largcol2, $text_height, price($object->total_ttc - $deja_regle - $creditnoteamount - $depositsamount), $useborder, 'R', 1);

				$resteapayer=0;
			}

			$cur_posy += $tab2_hl;
			$pdf->SetTextColor($this->BLACK['r'], $this->BLACK['g'], $this->BLACK['b']);
			$pdf->SetFillColor($this->CC_LIGHT_GREY['r'], $this->CC_LIGHT_GREY['g'], $this->CC_LIGHT_GREY['b']);
			$pdf->SetXY($col1x, $cur_posy + $marguin);
			$pdf->MultiCell($col2x-$col1x, $text_height, $outputlangs->transnoentities("RemainderToPay"), $useborder, 'L', 1);
			$pdf->SetXY($col2x, $cur_posy + $marguin);
			$pdf->MultiCell($largcol2, $text_height, price($resteapayer), $useborder, 'R', 1);

			// Fin
			$pdf->SetFont($ubuntu['light']['normal'],'', $default_font_size - 1);
			$pdf->SetTextColor($this->BLACK['r'], $this->BLACK['g'], $this->BLACK['b']);
		}

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
	 *   @param		int			$hidetop		1=Hide top bar of array and title, 0=Hide nothing, -1=Hide only title
	 *   @param		int			$hidebottom		Hide bottom bar of array
	 *   @return	void
	 */
	function _tableau(&$pdf, $tab_top, $tab_height, $nexY, $outputlangs, $hidetop=0, $hidebottom=0)
	{
		global $conf;

		$outputlangs->load('cc_extras');

		// Force to disable hidetop and hidebottom
		$hidebottom=0;
		if ($hidetop) { $hidetop=0; }

		$ubuntu = $this->get_ubuntu_font_array($outputlangs);
		$default_font_size = pdf_getPDFFontSize($outputlangs);

		// Amount in (at tab_top - 1)
		$pdf->SetTextColor($this->BLACK['r'], $this->BLACK['g'], $this->BLACK['b']);
		$pdf->SetFont($ubuntu['light']['normal'],'', $default_font_size - 2);

		if (empty($hidetop))
		{
			$titre = $outputlangs->transnoentities("AmountInCurrency",$outputlangs->transnoentitiesnoconv("Currency".$conf->currency));
			$pdf->SetXY($this->page_largeur - $this->marge_droite - ($pdf->GetStringWidth($titre) + 3), $tab_top - $this->ONE_MORE_LINE -1);
			$pdf->MultiCell(($pdf->GetStringWidth($titre) + 3), 2, $titre);
		}

		$pdf->SetTextColor($this->WHITE['r'], $this->WHITE['g'], $this->WHITE['b']);
		$pdf->SetFont($ubuntu['regular']['normal'],'', $default_font_size);

		// Tab header rectangle
		$legend_tab_height = 2;
		$pdf->SetFillColor($this->CC_DARK_GREY['r'], $this->CC_DARK_GREY['g'], $this->CC_DARK_GREY['b']);
		$pdf->SetXY($this->marge_gauche, $tab_top);
		$pdf->MultiCell($this->page_largeur-$this->marge_gauche-$this->marge_droite, $legend_tab_height + 4, '', 1, 'R', true);

		if (empty($hidetop))
		{
			$pdf->line($this->marge_gauche, $tab_top+5, $this->page_largeur-$this->marge_droite, $tab_top+5);	// line prend une position y en 2eme param et 4eme param

			$pdf->SetXY($this->posxdesc-1, $tab_top+1);
			$pdf->MultiCell(108,2, $outputlangs->transnoentities("Designation"),'','L');
		}

		if (empty($conf->global->MAIN_GENERATE_DOCUMENTS_WITHOUT_VAT))
		{
			$pdf->line($this->posxtva-1, $tab_top, $this->posxtva-1, $tab_top + $tab_height);
			if (empty($hidetop))
			{
				$pdf->SetXY($this->posxtva-4, $tab_top+1);
				$pdf->MultiCell($this->posxup-$this->posxtva+3,2, $outputlangs->transnoentities("PriceUHT"),'','R');
			}
		}

		$pdf->line($this->posxup-1, $tab_top, $this->posxup-1, $tab_top + $tab_height);
		if (empty($hidetop))
		{
			if ($this->atleastonediscount)
			{
				$pdf->SetXY($this->posxup-2, $tab_top+1);
				$pdf->MultiCell($this->posxqty-$this->posxup-1,2, $outputlangs->transnoentities("ReductionShort"),'','R');
			}
		}

		if ($this->atleastonediscount)
		{
			$pdf->line($this->posxqty, $tab_top, $this->posxqty, $tab_top + $tab_height);
		}

		//$pdf->line($this->posxqty-1, $tab_top, $this->posxqty-1, $tab_top + $tab_height);
		if (empty($hidetop))
		{
			$pdf->SetXY($this->posxqty-10, $tab_top+1);
			$pdf->MultiCell($this->posxdiscount-$this->posxqty+9,2, "TVA (%)",'','R');
		}

		$pdf->line($this->posxdiscount-1, $tab_top, $this->posxdiscount-1, $tab_top + $tab_height);
		if (empty($hidetop))
		{
			$pdf->SetXY($this->posxdiscount-2, $tab_top+1);
			$pdf->MultiCell($this->postotalht-$this->posxdiscount+1,2, "TVA",'','R');
		}

		$pdf->line($this->postotalht-1, $tab_top, $this->postotalht-1, $tab_top + $tab_height);
		if (empty($hidetop))
		{
			$pdf->SetXY($this->postotalht-2, $tab_top+1);
			$pdf->MultiCell(30,2, $outputlangs->transnoentities("TotalTTC"),'','R');
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
		if($object->statut==0 && (! empty($conf->global->FACTURE_DRAFT_WATERMARK)) )
		{
			pdf_watermark($pdf,$outputlangs,$this->page_hauteur,$this->page_largeur,'mm',$conf->global->FACTURE_DRAFT_WATERMARK);
		}

		$pdf->SetTextColor($this->BLACK['r'],$this->BLACK['g'],$this->BLACK['b']);
		$pdf->SetFont($ubuntu['bold']['normal'],'B', $default_font_size + 3);

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

		$pdf->SetFont($ubuntu['bold']['normal'],'B', $default_font_size);
		$pdf->SetXY($posx,$posy);
		$pdf->SetTextColor($this->BLACK['r'],$this->BLACK['g'],$this->BLACK['b']);
		$title=$outputlangs->transnoentities("Invoice");
		if ($object->type == 1) $title=$outputlangs->transnoentities("InvoiceReplacement");
		if ($object->type == 2) $title=$outputlangs->transnoentities("InvoiceAvoir");
		if ($object->type == 3) $title=$outputlangs->transnoentities("InvoiceDeposit");
		if ($object->type == 4) $title=$outputlangs->transnoentities("InvoiceProFormat");
		$pdf->MultiCell(100, 3, $title, '', 'R');

		$pdf->SetFont($ubuntu['bold']['normal'],'B',$default_font_size);

		$posy+=$this->ONE_MORE_LINE;
		$pdf->SetXY($posx,$posy);
		$pdf->SetTextColor($this->BLACK['r'],$this->BLACK['g'],$this->BLACK['b']);
		$pdf->MultiCell(100, 4, $outputlangs->transnoentities("Ref")." : " . $outputlangs->convToOutputCharset($object->ref), '', 'R');

		$pdf->SetFont($ubuntu['light']['normal'],'', $default_font_size);

		if ($object->ref_client)
		{
			$posy+=$this->ONE_MORE_LINE;
			$pdf->SetXY($posx,$posy);
			$pdf->SetTextColor($this->BLACK['r'],$this->BLACK['g'],$this->BLACK['b']);
			$pdf->MultiCell(100, 3, $outputlangs->transnoentities("RefCustomer")." : " . $outputlangs->convToOutputCharset($object->ref_client), '', 'R');
		}

		$objectidnext=$object->getIdReplacingInvoice('validated');
		if ($object->type == 0 && $objectidnext)
		{
			$objectreplacing=new Facture($this->db);
			$objectreplacing->fetch($objectidnext);

			$posy+=$this->ONE_MORE_LINE;
			$pdf->SetXY($posx,$posy);
			$pdf->SetTextColor($this->BLACK['r'],$this->BLACK['g'],$this->BLACK['b']);
			$pdf->MultiCell(100, 3, $outputlangs->transnoentities("ReplacementByInvoice").' : '.$outputlangs->convToOutputCharset($objectreplacing->ref), '', 'R');
		}
		if ($object->type == 1)
		{
			$objectreplaced=new Facture($this->db);
			$objectreplaced->fetch($object->fk_facture_source);

			$posy+=$this->ONE_MORE_LINE;
			$pdf->SetXY($posx,$posy);
			$pdf->SetTextColor($this->BLACK['r'],$this->BLACK['g'],$this->BLACK['b']);
			$pdf->MultiCell(100, 3, $outputlangs->transnoentities("ReplacementInvoice").' : '.$outputlangs->convToOutputCharset($objectreplaced->ref), '', 'R');
		}
		if ($object->type == 2)
		{
			$objectreplaced=new Facture($this->db);
			$objectreplaced->fetch($object->fk_facture_source);

			$posy+=$this->ONE_MORE_LINE;
			$pdf->SetXY($posx,$posy);
			$pdf->SetTextColor($this->BLACK['r'],$this->BLACK['g'],$this->BLACK['b']);
			$pdf->MultiCell(100, 3, $outputlangs->transnoentities("CorrectionInvoice").' : '.$outputlangs->convToOutputCharset($objectreplaced->ref), '', 'R');
		}

		$posy+=$this->ONE_MORE_LINE;
		$pdf->SetXY($posx,$posy);
		$pdf->SetTextColor($this->BLACK['r'],$this->BLACK['g'],$this->BLACK['b']);
		$pdf->MultiCell(100, 3, $outputlangs->transnoentities("DateInvoice")." : " . dol_print_date($object->date,"day",false,$outputlangs), '', 'R');

		if ($object->type != 2)
		{
			$posy+=$this->ONE_MORE_LINE;
			$pdf->SetXY($posx,$posy);
			$pdf->SetTextColor($this->BLACK['r'],$this->BLACK['g'],$this->BLACK['b']);
			$pdf->MultiCell(100, 3, $outputlangs->transnoentities("DateEcheance")." : " . dol_print_date($object->date_lim_reglement,"day",false,$outputlangs,true), '', 'R');
		}

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
			
			// If BILLING contact defined on invoice, we use it
			$usecontact=false;
			$arrayidcontact=$object->getIdContact('internal','SALESREPFOLL');

			if (count($arrayidcontact) > 0)
			{
				$usecontact=true;
				$result=$object->fetch_user($arrayidcontact[0]);
				$contact_emetteur = $object->user;
			}
			else {
				$contact_emetteur = null;
			}

			//$carac_emetteur = $this->pdf_build_address($outputlangs, $this->emetteur, $contact_emetteur, 'internal');
			$carac_emetteur=pdf_build_address($outputlangs,$this->emetteur,$object->user,($usecontact?$object->user:''),$usecontact,'source');

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
			$pdf->SetTextColor($this->BLACK['r'],$this->BLACK['g'],$this->BLACK['b']);

			// Show sender name
			$pdf->SetXY($posx+$retrait_cadre,$posy+3);
			$pdf->SetFont($ubuntu['bold']['normal'],'B', $default_font_size);
			$pdf->MultiCell(80, 4, $outputlangs->convToOutputCharset($this->emetteur->name), 0, 'L');

			// Show sender information
			$pdf->SetXY($posx+$retrait_cadre,$posy+10);
			$pdf->SetFont($ubuntu['light']['normal'],'', $default_font_size - 1);
			$pdf->MultiCell(80, 4, $carac_emetteur, 0, 'L');


			// If BILLING contact defined on invoice, we use it
			$usecontact=false;
			$arrayidcontact=$object->getIdContact('external','BILLING');

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
			$pdf->MultiCell($widthrecbox, 5, $outputlangs->transnoentities("BillTo"),0,'L');
			$pdf->Rect($posx, $posy, $widthrecbox, $hautcadre);

			// Show recipient name
			$pdf->SetTextColor($this->BLACK['r'],$this->BLACK['g'],$this->BLACK['b']);
			$pdf->SetXY($posx+$retrait_cadre,$posy+3);
			$pdf->SetFont($ubuntu['bold']['normal'],'B', $default_font_size);
			$pdf->MultiCell($widthrecbox, 4, $carac_client_name, 0, 'L');

			// Show recipient information
			$pdf->SetFont($ubuntu['light']['normal'],'', $default_font_size - 1);
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
        if(!empty($company->tva_intra)) {
            $adresse[] = 'Num. TVA : '.$company->tva_intra;
        }
		
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
		//return pdf_pagefoot($pdf,$outputlangs,'FACTURE_FREE_TEXT',$this->emetteur,$this->marge_basse,$this->marge_gauche,$this->page_hauteur,$object,0,$hidefreetext);
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
		$line3=array();
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
			$field=$outputlangs->transcountrynoentities("ProfId1",$fromcompany->country_code);
			$real_field = (preg_match('/\((.*)\)/i',$field,$reg)) ? $reg[1] : '';
			$line3[] = $real_field." : ".$outputlangs->convToOutputCharset($fromcompany->idprof1);
		}
		// Prof Id 2
		if ($fromcompany->idprof2)
		{
			$field=$outputlangs->transcountrynoentities("ProfId2",$fromcompany->country_code);
			$real_field = (preg_match('/\((.*)\)/i',$field,$reg)) ? $reg[1] : '';
			$line3[] = $real_field." : ".$outputlangs->convToOutputCharset($fromcompany->idprof2);
		}
		$line3 = implode(' - ', $line3);

		// Line 4 of company infos
		$line4=array();
		// Prof Id 3
		if ($fromcompany->idprof3)
		{
			$field=$outputlangs->transcountrynoentities("ProfId3",$fromcompany->country_code);
			$real_field = (preg_match('/\((.*)\)/i',$field,$reg)) ? $reg[1] : '';
			$line4[] = $real_field." : ".$outputlangs->convToOutputCharset($fromcompany->idprof3);
		}
		// Prof Id 4
		if ($fromcompany->idprof4)
		{
			$field=$outputlangs->transcountrynoentities("ProfId4",$fromcompany->country_code);
			$real_field = (preg_match('/\((.*)\)/i',$field,$reg)) ? $reg[1] : '';
			$line4[] = $real_field." : ".$outputlangs->convToOutputCharset($fromcompany->idprof4);
		}
		// IntraCommunautary VAT
		if ($fromcompany->tva_intra != '')
		{
			$line4[] = $outputlangs->transnoentities("VATIntraShort")." : ".$outputlangs->convToOutputCharset($fromcompany->tva_intra);
		}
		$line4 = implode(' - ', $line4);

		$pdf->SetFont($ubuntu['bold']['normal'],'',$default_font_size);
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
		$posy=$marginwithfooter+0;

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

		if (! empty($line1))
		{
			$pdf->SetFont($ubuntu['bold']['normal'],'',$default_font_size);
			$pdf->SetXY($dims['lm'],-$posy);
			$pdf->MultiCell(200, 2, $line1, 0, 'C', 0);
			$posy-= $this->ONE_MORE_LINE/2;
			$pdf->SetFont($ubuntu['bold']['normal'],'',$default_font_size);
		}

		if (! empty($line2))
		{
			$pdf->SetFont($ubuntu['bold']['normal'],'',$default_font_size);
			$pdf->SetXY($dims['lm'],-$posy);
			$pdf->MultiCell(200, 2, $line2, 0, 'C', 0);
			$posy-= $this->ONE_MORE_LINE/2;
			$pdf->SetFont($ubuntu['bold']['normal'],'',$default_font_size);
		}

		if (! empty($line3))
		{
			$pdf->SetXY($dims['lm'],-$posy);
			$pdf->MultiCell(200, 2, $line3, 0, 'C', 0);
		}

		if (! empty($line4))
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
